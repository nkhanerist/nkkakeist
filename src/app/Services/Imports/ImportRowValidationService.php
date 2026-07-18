<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\Category;
use App\Models\Import;
use App\Models\ImportRow;
use App\Models\Subcategory;
use App\Services\ClassificationRules\ApplyClassificationRulesService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportRowValidationService
{
    public function __construct(
        private readonly DuplicateDetectionService $duplicateDetectionService,
        private readonly ApplyClassificationRulesService $applyClassificationRulesService,
        private readonly ResolveTransferImportRowService $resolveTransferImportRowService,
    ) {}

    public function handle(Import $import): Import
    {
        $import->loadMissing('user', 'account', 'importRows');

        $accounts = $import->user->accounts()->get();
        $categories = $import->user->categories()->get();
        $subcategories = $import->user->subcategories()->get();
        $classificationRules = $import->user->classificationRules()
            ->with('subcategory')
            ->where('is_active', true)
            ->orderBy('priority')
            ->orderBy('id')
            ->get();
        $duplicateRows = 0;

        DB::transaction(function () use ($import, $accounts, $categories, $subcategories, $classificationRules, &$duplicateRows): void {
            $seenDuplicateHashes = [];
            $seenTransferMirrorPairs = [];
            $seenTransferMirrorBasePairs = [];
            $historicalTransferDuplicateCounts = $this->historicalTransferDuplicateCounts($import, $accounts);
            $historicalTransferFallbackCounts = $this->historicalTransferFallbackCounts($import, $accounts);
            $seenTransferDuplicateCounts = [];
            $seenTransferFallbackCounts = [];

            foreach ($import->importRows as $importRow) {
                $hasAmbiguousAccountMatch = $this->hasAmbiguousAccountMatch($import, $importRow, $accounts);
                $resolvedAccountId = $this->resolveAccountId($import, $importRow, $accounts);
                $originalResolvedAccountId = $resolvedAccountId;
                $transferResolution = $importRow->detected_type === 'transfer'
                    ? $this->resolveTransferImportRowService->handle(
                        $import,
                        $importRow,
                        $accounts,
                        $resolvedAccountId,
                        $hasAmbiguousAccountMatch,
                    )
                    : [
                        'resolved_account_id' => $resolvedAccountId,
                        'resolved_transfer_account_id' => null,
                        'validation_errors' => [],
                    ];
                $resolvedAccountId = $transferResolution['resolved_account_id'];
                $csvResolvedCategory = $this->resolveCategory($importRow, $categories);
                $csvResolvedSubcategoryId = $this->resolveSubcategoryId(
                    $importRow,
                    $csvResolvedCategory?->id,
                    $subcategories,
                );
                $classificationRuleResult = $this->applyClassificationRulesService->handle(
                    $importRow,
                    $classificationRules,
                    $csvResolvedCategory?->id,
                    $csvResolvedSubcategoryId,
                );
                if ($importRow->detected_type === 'transfer') {
                    $classificationRuleResult['resolved_category_id'] = null;
                    $classificationRuleResult['resolved_subcategory_id'] = null;
                    $classificationRuleResult['matched_classification_rule_id'] = null;
                    $classificationRuleResult['rule_applied_fields'] = [];
                    $classificationRuleResult['is_calculation_target'] = false;
                }
                $resolvedAffectsAccountBalance = $importRow->detected_type === 'transfer'
                    ? true
                    : ($importRow->affects_account_balance
                        ?? $classificationRuleResult['is_calculation_target']
                        ?? true);
                $resolvedCategory = $this->resolveCategoryById(
                    $classificationRuleResult['resolved_category_id'],
                    $categories,
                );
                $resolvedSubcategoryId = $classificationRuleResult['resolved_subcategory_id'];

                $validationErrors = $this->validationErrors(
                    $import,
                    $importRow,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                    $hasAmbiguousAccountMatch,
                    $resolvedCategory,
                    $transferResolution['validation_errors'],
                );
                $duplicateHash = $this->duplicateHash(
                    $import,
                    $importRow,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $transferDuplicateKey = $this->transferDuplicateKey(
                    $importRow,
                    $accounts,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $transferDuplicateFallbackKey = $this->transferDuplicateFallbackKey(
                    $importRow,
                    $accounts,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $isExistingTransferDuplicate = $this->isExistingTransferDuplicate(
                    $historicalTransferDuplicateCounts,
                    $historicalTransferFallbackCounts,
                    $seenTransferDuplicateCounts,
                    $seenTransferFallbackCounts,
                    $transferDuplicateKey,
                    $transferDuplicateFallbackKey,
                );
                $isExistingDuplicate = $importRow->detected_type === 'transfer'
                    ? ($import->source_name === 'jre_point'
                        ? $this->duplicateDetectionService->isDuplicateCandidate(
                            $import->user,
                            $importRow->transaction_date?->format('Y-m-d') ?? '',
                            (string) $importRow->amount,
                            $importRow->merchant_name,
                            $importRow->description,
                            'transfer',
                            $resolvedAccountId,
                            null,
                            $transferResolution['resolved_transfer_account_id'],
                        )
                        : $isExistingTransferDuplicate)
                    : (
                        $duplicateHash !== null
                        && $importRow->status !== 'imported'
                        && $this->duplicateDetectionService->isDuplicateCandidate(
                            $import->user,
                            $importRow->transaction_date?->format('Y-m-d') ?? '',
                            (string) $importRow->amount,
                            $importRow->merchant_name,
                            $importRow->description,
                            (string) $importRow->detected_type,
                            $resolvedAccountId,
                            $this->externalId($importRow),
                            $transferResolution['resolved_transfer_account_id'],
                        )
                    );
                $isImportRowDuplicate = $importRow->detected_type === 'transfer'
                    ? false
                    : (
                        $duplicateHash !== null
                        && $this->duplicateDetectionService->isImportRowDuplicateCandidate(
                            $import->user,
                            $duplicateHash,
                            $import->id,
                        )
                    );
                $transferMirrorPairHash = $this->transferMirrorPairHash(
                    $importRow,
                    $accounts,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $transferMirrorBaseHash = $this->transferMirrorBaseHash(
                    $importRow,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $transferMirrorSideKey = $this->transferMirrorSideKey(
                    $originalResolvedAccountId,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $isCsvExactMirrorDuplicate = $this->isCsvTransferMirrorDuplicate(
                    $seenTransferMirrorPairs,
                    $transferMirrorPairHash,
                    $transferMirrorSideKey,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $isCsvBaseMirrorDuplicate = $this->isCsvTransferMirrorDuplicate(
                    $seenTransferMirrorBasePairs,
                    $transferMirrorBaseHash,
                    $transferMirrorSideKey,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $currentTransferDescriptorKey = $this->transferMirrorDescriptorKey(
                    $importRow,
                    $accounts,
                    $resolvedAccountId ?? 0,
                    $transferResolution['resolved_transfer_account_id'] ?? 0,
                );
                $sameSideBaseCount = $this->transferMirrorSameSideCount(
                    $seenTransferMirrorBasePairs,
                    $transferMirrorBaseHash,
                    $transferMirrorSideKey,
                );
                $isCsvMirrorDuplicate = $isCsvExactMirrorDuplicate
                    || (
                        $currentTransferDescriptorKey === ''
                        && $sameSideBaseCount > 0
                        && $isCsvBaseMirrorDuplicate
                    );
                $isCsvDuplicate = $importRow->detected_type === 'transfer'
                    ? $isCsvMirrorDuplicate
                    : (($duplicateHash !== null && isset($seenDuplicateHashes[$duplicateHash])) || $isCsvMirrorDuplicate);
                $isDuplicateCandidate = $isExistingDuplicate || $isImportRowDuplicate || $isCsvDuplicate;

                if ($isDuplicateCandidate) {
                    $duplicateRows++;
                }

                if ($duplicateHash !== null) {
                    $seenDuplicateHashes[$duplicateHash] = true;
                }

                $this->rememberTransferDuplicateKey(
                    $seenTransferDuplicateCounts,
                    $transferDuplicateKey,
                );
                $this->rememberTransferDuplicateKey(
                    $seenTransferFallbackCounts,
                    $transferDuplicateFallbackKey,
                );

                $this->rememberTransferMirrorPair(
                    $seenTransferMirrorPairs,
                    $transferMirrorPairHash,
                    $transferMirrorSideKey,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );
                $this->rememberTransferMirrorPair(
                    $seenTransferMirrorBasePairs,
                    $transferMirrorBaseHash,
                    $transferMirrorSideKey,
                    $resolvedAccountId,
                    $transferResolution['resolved_transfer_account_id'],
                );

                $importRow->update([
                    'resolved_account_id' => $resolvedAccountId,
                    'resolved_transfer_account_id' => $transferResolution['resolved_transfer_account_id'],
                    'resolved_category_id' => $resolvedCategory?->id,
                    'resolved_subcategory_id' => $resolvedSubcategoryId,
                    'matched_classification_rule_id' => $classificationRuleResult['matched_classification_rule_id'],
                    'rule_applied_fields' => $classificationRuleResult['rule_applied_fields'],
                    'resolved_is_calculation_target' => $classificationRuleResult['is_calculation_target'],
                    'resolved_affects_account_balance' => $resolvedAffectsAccountBalance,
                    'duplicate_hash' => $duplicateHash,
                    'is_duplicate_candidate' => $isDuplicateCandidate,
                    'validation_errors' => $validationErrors === [] ? null : $validationErrors,
                    'transfer_resolution' => $importRow->detected_type === 'transfer'
                        ? [
                            'source_resolution_type' => $transferResolution['source_resolution_type'] ?? null,
                            'source_resolution_message' => $transferResolution['source_resolution_message'] ?? null,
                            'destination_resolution_type' => $transferResolution['destination_resolution_type'] ?? null,
                            'destination_resolution_message' => $transferResolution['destination_resolution_message'] ?? null,
                            'unresolved_reason' => $transferResolution['unresolved_reason'] ?? null,
                        ]
                        : null,
                    'status' => $validationErrors === [] ? 'ready' : 'error',
                ]);
            }

            $import->update([
                'duplicate_rows' => $duplicateRows,
                'status' => 'validated',
                'error_message' => null,
            ]);
        });

        return $import->fresh(['account', 'importRows']);
    }

    /**
     * @return array<int, string>
     */
    private function validationErrors(
        Import $import,
        ImportRow $importRow,
        ?int $resolvedAccountId,
        ?int $resolvedTransferAccountId,
        bool $hasAmbiguousAccountMatch,
        ?Category $resolvedCategory,
        array $transferResolutionErrors,
    ): array {
        $errors = [];

        if ($resolvedAccountId === null && $import->account_id === null && $importRow->account_name === null) {
            $errors[] = '取込先口座を特定できません。';
        }

        if ($hasAmbiguousAccountMatch) {
            $errors[] = '同名の口座が複数あるため取込先口座を特定できません。共通適用口座を選択してください。';
        }

        if ($importRow->detected_type === 'transfer') {
            $errors = [...$errors, ...$transferResolutionErrors];

            if (
                $resolvedAccountId !== null
                && $resolvedTransferAccountId !== null
                && $resolvedAccountId === $resolvedTransferAccountId
            ) {
                $errors[] = '振替元口座と振替先口座は同じにできません。';
            }
        }

        if ($importRow->transaction_date === null) {
            $errors[] = '取引日を解釈できません。';
        }

        if ($importRow->amount === null) {
            $errors[] = '金額を解釈できません。';
        }

        if (! in_array($importRow->detected_type, ['income', 'expense', 'transfer'], true)) {
            $errors[] = '取引種別を判定できません。';
        }

        if ($importRow->subcategory_name !== null && $importRow->category_name === null) {
            $errors[] = '中項目を使う場合は大項目が必要です。';
        }

        if (
            in_array($importRow->detected_type, ['income', 'expense'], true)
            && $resolvedCategory !== null
            && ! in_array($resolvedCategory->type, [$importRow->detected_type, 'both'], true)
        ) {
            $errors[] = '既存カテゴリの種別が取引種別と一致していません。';
        }

        return $errors;
    }

    private function duplicateHash(
        Import $import,
        ImportRow $importRow,
        ?int $accountId,
        ?int $transferAccountId = null,
    ): ?string {
        $externalId = $this->externalId($importRow);

        if ($externalId !== null) {
            return $this->duplicateDetectionService->buildHash(
                $import->user_id,
                $importRow->transaction_date?->format('Y-m-d') ?? '',
                (string) ($importRow->amount ?? ''),
                $importRow->merchant_name,
                $importRow->description,
                (string) $importRow->detected_type,
                $accountId,
                $externalId,
                $transferAccountId,
            );
        }

        if (
            $accountId === null
            || $importRow->transaction_date === null
            || $importRow->amount === null
            || (
                ! in_array($importRow->detected_type, ['income', 'expense'], true)
                && ! ($importRow->detected_type === 'transfer' && $transferAccountId !== null)
            )
        ) {
            return null;
        }

        return $this->duplicateDetectionService->buildHash(
            $import->user_id,
            $importRow->transaction_date->format('Y-m-d'),
            (string) $importRow->amount,
            $importRow->merchant_name,
            $importRow->description,
            $importRow->detected_type,
            $accountId,
            null,
            $transferAccountId,
        );
    }

    private function externalId(ImportRow $importRow): ?string
    {
        $externalId = $importRow->raw_payload['ID'] ?? $importRow->raw_payload['id'] ?? null;

        if (! is_string($externalId)) {
            return null;
        }

        $normalized = trim($externalId);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function resolveAccountId(Import $import, ImportRow $importRow, Collection $accounts): ?int
    {
        if ($importRow->detected_type === 'transfer') {
            if ($import->source_name === 'jre_point' && $import->account_id !== null) {
                return $import->account_id;
            }

            if ($importRow->account_name === null) {
                return null;
            }

            $matchedAccounts = $this->matchedSourceAccounts($importRow, $accounts);

            if ($matchedAccounts->count() !== 1) {
                return null;
            }

            return $matchedAccounts->first()?->id;
        }

        if ($import->account_id !== null) {
            return $import->account_id;
        }

        if ($importRow->account_name === null) {
            return $import->account_id;
        }

        $matchedAccounts = $this->matchedSourceAccounts($importRow, $accounts);

        if ($matchedAccounts->count() !== 1) {
            return null;
        }

        return $matchedAccounts->first()?->id;
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function resolveCategoryById(?int $categoryId, Collection $categories): ?Category
    {
        if ($categoryId === null) {
            return null;
        }

        return $categories->first(
            fn (Category $category): bool => $category->id === $categoryId,
        );
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function hasAmbiguousAccountMatch(Import $import, ImportRow $importRow, Collection $accounts): bool
    {
        if ($import->source_name === 'jre_point' && $import->account_id !== null) {
            return false;
        }

        if ($importRow->detected_type !== 'transfer' && $import->account_id !== null) {
            return false;
        }

        if ($importRow->account_name === null) {
            return false;
        }

        return $this->matchedSourceAccounts($importRow, $accounts)->count() > 1;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    private function matchedSourceAccounts(ImportRow $importRow, Collection $accounts): Collection
    {
        $normalizedAccountName = $this->normalizeName($importRow->account_name ?? '');

        return $accounts->filter(function (Account $account) use ($normalizedAccountName): bool {
            $candidates = collect([$account->name, ...($account->import_aliases ?? [])])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeName($value))
                ->unique();

            return $candidates->contains($normalizedAccountName);
        })->values();
    }

    /**
     * @param  Collection<int, Category>  $categories
     */
    private function resolveCategory(ImportRow $importRow, Collection $categories): ?Category
    {
        if ($importRow->category_name === null) {
            return null;
        }

        $normalizedCategoryName = $this->normalizeName($importRow->category_name);
        $detectedType = $importRow->detected_type;

        if (in_array($detectedType, ['income', 'expense'], true)) {
            $matched = $categories->first(function (Category $category) use ($normalizedCategoryName, $detectedType): bool {
                return $this->normalizeName($category->name) === $normalizedCategoryName
                    && in_array($category->type, [$detectedType, 'both'], true);
            });

            if ($matched !== null) {
                return $matched;
            }
        }

        return $categories->first(
            fn (Category $category): bool => $this->normalizeName($category->name) === $normalizedCategoryName,
        );
    }

    /**
     * @param  Collection<int, Subcategory>  $subcategories
     */
    private function resolveSubcategoryId(ImportRow $importRow, ?int $categoryId, Collection $subcategories): ?int
    {
        if ($categoryId === null || $importRow->subcategory_name === null) {
            return null;
        }

        $matched = $subcategories->first(function (Subcategory $subcategory) use ($importRow, $categoryId): bool {
            return $subcategory->category_id === $categoryId
                && $this->normalizeName($subcategory->name) === $this->normalizeName($importRow->subcategory_name);
        });

        return $matched?->id;
    }

    private function normalizeName(string $value): string
    {
        return Str::lower(Str::squish(mb_convert_kana($value, 'asKV', 'UTF-8')));
    }

    private function transferMirrorOriginalSideKey(
        ImportRow $importRow,
        Collection $accounts,
        int $accountId,
        int $transferAccountId,
    ): ?int {
        $accountName = $importRow->account_name;

        if (! is_string($accountName) || trim($accountName) === '') {
            return null;
        }

        $normalizedAccountName = $this->normalizeName($accountName);
        $sourceAccount = $accounts->first(fn (Account $account): bool => $account->id === $accountId);
        $destinationAccount = $accounts->first(fn (Account $account): bool => $account->id === $transferAccountId);

        foreach ([$sourceAccount, $destinationAccount] as $candidateAccount) {
            if (! $candidateAccount instanceof Account) {
                continue;
            }

            $tokens = $this->accountMatchTokens($candidateAccount);

            if ($tokens->contains($normalizedAccountName)) {
                return $candidateAccount->id;
            }
        }

        return null;
    }

    /**
     * @return array<string, int>
     */
    private function historicalTransferDuplicateCounts(Import $import, Collection $accounts): array
    {
        $counts = [];

        $historicalTransactions = $import->user->transactions()
            ->where('type', 'transfer')
            ->get(['transaction_date', 'amount', 'account_id', 'transfer_account_id', 'merchant_name', 'description', 'external_id']);

        foreach ($historicalTransactions as $transaction) {
            $key = $this->transferDuplicateKeyFromValues(
                $transaction->transaction_date?->format('Y-m-d'),
                (string) $transaction->amount,
                $transaction->account_id,
                $transaction->transfer_account_id,
                $transaction->external_id,
                $this->transferDuplicateDescriptorKeyFromValues(
                    $transaction->merchant_name,
                    $transaction->description,
                    $accounts,
                    $transaction->account_id,
                    $transaction->transfer_account_id,
                ),
            );

            if ($key === null) {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function historicalTransferFallbackCounts(Import $import, Collection $accounts): array
    {
        $counts = [];

        $historicalTransactions = $import->user->transactions()
            ->where('type', 'transfer')
            ->get(['transaction_date', 'amount', 'account_id', 'transfer_account_id', 'merchant_name', 'description']);

        foreach ($historicalTransactions as $transaction) {
            $key = $this->transferDuplicateFallbackKeyFromValues(
                $transaction->transaction_date?->format('Y-m-d'),
                (string) $transaction->amount,
                $transaction->account_id,
                $transaction->transfer_account_id,
                $this->transferDuplicateDescriptorKeyFromValues(
                    $transaction->merchant_name,
                    $transaction->description,
                    $accounts,
                    $transaction->account_id,
                    $transaction->transfer_account_id,
                ),
            );

            if ($key === null) {
                continue;
            }

            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        return $counts;
    }

    private function transferDuplicateKey(
        ImportRow $importRow,
        Collection $accounts,
        ?int $accountId,
        ?int $transferAccountId,
    ): ?string {
        return $this->transferDuplicateKeyFromValues(
            $importRow->transaction_date?->format('Y-m-d'),
            $importRow->amount !== null ? (string) $importRow->amount : null,
            $accountId,
            $transferAccountId,
            $this->externalId($importRow),
            $this->transferDuplicateDescriptorKeyFromValues(
                $importRow->merchant_name,
                $importRow->description,
                $accounts,
                $accountId,
                $transferAccountId,
            ),
        );
    }

    private function transferDuplicateFallbackKey(
        ImportRow $importRow,
        Collection $accounts,
        ?int $accountId,
        ?int $transferAccountId,
    ): ?string {
        return $this->transferDuplicateFallbackKeyFromValues(
            $importRow->transaction_date?->format('Y-m-d'),
            $importRow->amount !== null ? (string) $importRow->amount : null,
            $accountId,
            $transferAccountId,
            $this->transferDuplicateDescriptorKeyFromValues(
                $importRow->merchant_name,
                $importRow->description,
                $accounts,
                $accountId,
                $transferAccountId,
            ),
        );
    }

    private function transferDuplicateKeyFromValues(
        ?string $transactionDate,
        ?string $amount,
        ?int $accountId,
        ?int $transferAccountId,
        ?string $externalId,
        string $descriptorKey,
    ): ?string {
        if (
            $transactionDate === null
            || $amount === null
            || $accountId === null
            || $transferAccountId === null
        ) {
            return null;
        }

        return implode('|', [
            $transactionDate,
            $this->normalizeDuplicateAmount($amount),
            'transfer',
            $accountId,
            $transferAccountId,
            $externalId !== null ? 'id:'.$externalId : 'desc:'.$descriptorKey,
        ]);
    }

    private function transferDuplicateFallbackKeyFromValues(
        ?string $transactionDate,
        ?string $amount,
        ?int $accountId,
        ?int $transferAccountId,
        string $descriptorKey,
    ): ?string {
        if (
            $transactionDate === null
            || $amount === null
            || $accountId === null
            || $transferAccountId === null
        ) {
            return null;
        }

        return implode('|', [
            $transactionDate,
            $this->normalizeDuplicateAmount($amount),
            'transfer',
            $accountId,
            $transferAccountId,
            'desc:'.$descriptorKey,
        ]);
    }

    /**
     * @param  array<string, int>  $historicalTransferDuplicateCounts
     * @param  array<string, int>  $seenTransferDuplicateCounts
     */
    private function isExistingTransferDuplicate(
        array $historicalTransferDuplicateCounts,
        array $historicalTransferFallbackCounts,
        array $seenTransferDuplicateCounts,
        array $seenTransferFallbackCounts,
        ?string $transferDuplicateKey,
        ?string $transferDuplicateFallbackKey,
    ): bool {
        $hasExactDuplicate = $transferDuplicateKey !== null
            && ($historicalTransferDuplicateCounts[$transferDuplicateKey] ?? 0)
                > ($seenTransferDuplicateCounts[$transferDuplicateKey] ?? 0);

        if ($hasExactDuplicate) {
            return true;
        }

        return $transferDuplicateFallbackKey !== null
            && ($historicalTransferFallbackCounts[$transferDuplicateFallbackKey] ?? 0)
                > ($seenTransferFallbackCounts[$transferDuplicateFallbackKey] ?? 0);
    }

    /**
     * @param  array<string, int>  $seenTransferDuplicateCounts
     */
    private function rememberTransferDuplicateKey(
        array &$seenTransferDuplicateCounts,
        ?string $transferDuplicateKey,
    ): void {
        if ($transferDuplicateKey === null) {
            return;
        }

        $seenTransferDuplicateCounts[$transferDuplicateKey] =
            ($seenTransferDuplicateCounts[$transferDuplicateKey] ?? 0) + 1;
    }

    private function normalizeDuplicateAmount(string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function transferMirrorPairHash(
        ImportRow $importRow,
        Collection $accounts,
        ?int $accountId,
        ?int $transferAccountId,
    ): ?string {
        if (
            $importRow->detected_type !== 'transfer'
            || $accountId === null
            || $transferAccountId === null
            || $importRow->transaction_date === null
            || $importRow->amount === null
        ) {
            return null;
        }

        return hash('sha256', implode('|', [
            $importRow->transaction_date->format('Y-m-d'),
            (string) $importRow->amount,
            'transfer',
            $accountId,
            $transferAccountId,
            $this->transferMirrorDescriptorKey($importRow, $accounts, $accountId, $transferAccountId),
        ]));
    }

    private function transferMirrorBaseHash(
        ImportRow $importRow,
        ?int $accountId,
        ?int $transferAccountId,
    ): ?string {
        if (
            $importRow->detected_type !== 'transfer'
            || $accountId === null
            || $transferAccountId === null
            || $importRow->transaction_date === null
            || $importRow->amount === null
        ) {
            return null;
        }

        return hash('sha256', implode('|', [
            $importRow->transaction_date->format('Y-m-d'),
            (string) $importRow->amount,
            'transfer',
            $accountId,
            $transferAccountId,
        ]));
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function transferMirrorDescriptorKey(
        ImportRow $importRow,
        Collection $accounts,
        int $accountId,
        int $transferAccountId,
    ): string {
        return $this->transferDuplicateDescriptorKeyFromValues(
            $importRow->merchant_name,
            $importRow->description,
            $accounts,
            $accountId,
            $transferAccountId,
        );
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function transferDuplicateDescriptorKeyFromValues(
        ?string $merchantName,
        ?string $description,
        Collection $accounts,
        ?int $accountId,
        ?int $transferAccountId,
    ): string {
        $descriptor = $this->normalizeName($merchantName ?: $description ?: '');

        if ($descriptor === '' || $accountId === null || $transferAccountId === null) {
            return '';
        }

        $sourceAccount = $accounts->first(fn (Account $account): bool => $account->id === $accountId);
        $destinationAccount = $accounts->first(fn (Account $account): bool => $account->id === $transferAccountId);

        foreach ([$sourceAccount, $destinationAccount] as $account) {
            if (! $account instanceof Account) {
                continue;
            }

            foreach ($this->accountMatchTokens($account) as $token) {
                if ($token !== '') {
                    $descriptor = str_replace($token, '', $descriptor);
                }
            }
        }

        $descriptor = str_replace(
            ['引落', '振替', 'ﾁｬｰｼﾞ', 'チャージ', '支払', '支払い', '払戻', 'ｶｰﾄﾞ', 'カード', '手動登録', '既存データ', '口座', 'の'],
            '',
            $descriptor,
        );

        return $descriptor;
    }

    private function transferMirrorSideKey(
        ?int $originalResolvedAccountId,
        ?int $accountId,
        ?int $transferAccountId,
    ): ?int {
        if (
            $originalResolvedAccountId === null
            || $accountId === null
            || $transferAccountId === null
        ) {
            return null;
        }

        if (in_array($originalResolvedAccountId, [$accountId, $transferAccountId], true)) {
            return $originalResolvedAccountId;
        }

        return null;
    }

    /**
     * @param  array<string, array{paired:int, side_counts: array<int, int>}>  $seenTransferMirrorPairs
     */
    private function isCsvTransferMirrorDuplicate(
        array $seenTransferMirrorPairs,
        ?string $pairHash,
        ?int $sideKey,
        ?int $accountId,
        ?int $transferAccountId,
    ): bool {
        if (
            $pairHash === null
            || $sideKey === null
            || $accountId === null
            || $transferAccountId === null
        ) {
            return false;
        }

        $otherSideKey = $sideKey === $accountId ? $transferAccountId : $accountId;
        $pairState = $seenTransferMirrorPairs[$pairHash] ?? [
            'paired' => 0,
            'side_counts' => [],
        ];
        $otherSideCount = $pairState['side_counts'][$otherSideKey] ?? 0;

        return $otherSideCount > $pairState['paired'];
    }

    /**
     * @param  array<string, array{paired:int, side_counts: array<int, int>}>  $seenTransferMirrorPairs
     */
    private function rememberTransferMirrorPair(
        array &$seenTransferMirrorPairs,
        ?string $pairHash,
        ?int $sideKey,
        ?int $accountId,
        ?int $transferAccountId,
    ): void {
        if (
            $pairHash === null
            || $sideKey === null
            || $accountId === null
            || $transferAccountId === null
        ) {
            return;
        }

        if (! isset($seenTransferMirrorPairs[$pairHash])) {
            $seenTransferMirrorPairs[$pairHash] = [
                'paired' => 0,
                'side_counts' => [],
            ];
        }

        $otherSideKey = $sideKey === $accountId ? $transferAccountId : $accountId;

        if (($seenTransferMirrorPairs[$pairHash]['side_counts'][$otherSideKey] ?? 0) > $seenTransferMirrorPairs[$pairHash]['paired']) {
            $seenTransferMirrorPairs[$pairHash]['paired']++;
        }

        $seenTransferMirrorPairs[$pairHash]['side_counts'][$sideKey] =
            ($seenTransferMirrorPairs[$pairHash]['side_counts'][$sideKey] ?? 0) + 1;
    }

    /**
     * @param  array<string, array{paired:int, side_counts: array<int, int>}>  $seenTransferMirrorPairs
     */
    private function transferMirrorSameSideCount(
        array $seenTransferMirrorPairs,
        ?string $pairHash,
        ?int $sideKey,
    ): int {
        if ($pairHash === null || $sideKey === null) {
            return 0;
        }

        return $seenTransferMirrorPairs[$pairHash]['side_counts'][$sideKey] ?? 0;
    }

    /**
     * @return Collection<int, string>
     */
    private function accountMatchTokens(Account $account): Collection
    {
        return collect([$account->name, ...($account->import_aliases ?? [])])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeName($value))
            ->unique()
            ->values();
    }
}
