<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ResolveTransferImportRowService
{
    /**
     * @param  Collection<int, Account>  $accounts
     * @return array{
     *     resolved_account_id: ?int,
     *     resolved_transfer_account_id: ?int,
     *     validation_errors: array<int, string>,
     *     source_resolution_type: ?string,
     *     source_resolution_message: ?string,
     *     destination_resolution_type: ?string,
     *     destination_resolution_message: ?string,
     *     unresolved_reason: ?string
     * }
     */
    public function handle(
        Import $import,
        ImportRow $importRow,
        Collection $accounts,
        ?int $resolvedAccountId,
        bool $hasAmbiguousAccountMatch,
    ): array {
        $validationErrors = [];

        if ($resolvedAccountId === null) {
            if ($hasAmbiguousAccountMatch) {
                return [
                    'resolved_account_id' => null,
                    'resolved_transfer_account_id' => null,
                    'validation_errors' => [],
                    'source_resolution_type' => 'ambiguous',
                    'source_resolution_message' => '振替元口座は口座名・取込用別名の候補が複数あり、自動決定できません。',
                    'destination_resolution_type' => null,
                    'destination_resolution_message' => null,
                    'unresolved_reason' => '振替元口座候補が複数あります。',
                ];
            }

            $validationErrors[] = '振替元口座を特定できません。';

            return [
                'resolved_account_id' => null,
                'resolved_transfer_account_id' => null,
                'validation_errors' => $validationErrors,
                'source_resolution_type' => 'unresolved',
                'source_resolution_message' => 'CSV の保有金融機関と一致する振替元口座を見つけられませんでした。',
                'destination_resolution_type' => null,
                'destination_resolution_message' => null,
                'unresolved_reason' => '振替元口座を特定できません。',
            ];
        }

        $resolvedAccount = $accounts->first(
            fn (Account $account): bool => $account->id === $resolvedAccountId,
        );

        if ($resolvedAccount === null) {
            $validationErrors[] = '振替元口座を特定できません。';

            return [
                'resolved_account_id' => null,
                'resolved_transfer_account_id' => null,
                'validation_errors' => $validationErrors,
                'source_resolution_type' => 'unresolved',
                'source_resolution_message' => '振替元口座を取得できませんでした。',
                'destination_resolution_type' => null,
                'destination_resolution_message' => null,
                'unresolved_reason' => '振替元口座を特定できません。',
            ];
        }

        $sourceResolution = $this->sourceResolution($importRow, $resolvedAccount);
        $matchedAccounts = $this->matchedAccounts($importRow, $accounts, $resolvedAccount);
        $manualTransferAccount = $this->manualTransferAccount($importRow, $accounts, $resolvedAccount);

        if ($manualTransferAccount !== null) {
            $matchedAccounts = collect([$manualTransferAccount]);
        }

        if ($matchedAccounts->count() === 0) {
            $validationErrors[] = '振替先口座を特定できません。';

            return [
                'resolved_account_id' => $resolvedAccount->id,
                'resolved_transfer_account_id' => null,
                'validation_errors' => $validationErrors,
                'source_resolution_type' => $sourceResolution['type'],
                'source_resolution_message' => $sourceResolution['message'],
                'destination_resolution_type' => 'unresolved',
                'destination_resolution_message' => '摘要 / 説明 / メモから振替先口座を特定できませんでした。',
                'unresolved_reason' => '振替先口座を特定できません。',
            ];
        }

        if ($matchedAccounts->count() > 1) {
            $validationErrors[] = '振替先として一致する口座が複数あるため、相手口座を特定できません。';

            return [
                'resolved_account_id' => $resolvedAccount->id,
                'resolved_transfer_account_id' => null,
                'validation_errors' => $validationErrors,
                'source_resolution_type' => $sourceResolution['type'],
                'source_resolution_message' => $sourceResolution['message'],
                'destination_resolution_type' => 'ambiguous',
                'destination_resolution_message' => '振替先候補が複数あるため、自動決定できません。',
                'unresolved_reason' => '振替先候補が複数あります。',
            ];
        }

        $matchedAccount = $matchedAccounts->first();

        if ($matchedAccount === null) {
            $validationErrors[] = '振替先口座を特定できません。';

            return [
                'resolved_account_id' => $resolvedAccount->id,
                'resolved_transfer_account_id' => null,
                'validation_errors' => $validationErrors,
                'source_resolution_type' => $sourceResolution['type'],
                'source_resolution_message' => $sourceResolution['message'],
                'destination_resolution_type' => 'unresolved',
                'destination_resolution_message' => '振替先口座を取得できませんでした。',
                'unresolved_reason' => '振替先口座を特定できません。',
            ];
        }

        $destinationResolution = $this->destinationResolution($importRow, $resolvedAccount, $matchedAccount);
        [$sourceAccountId, $transferAccountId] = $this->resolveDirection(
            $importRow,
            $resolvedAccount,
            $matchedAccount,
        );

        if ($sourceAccountId === null || $transferAccountId === null) {
            return [
                'resolved_account_id' => $resolvedAccount->id,
                'resolved_transfer_account_id' => null,
                'validation_errors' => ['振替方向を安全に特定できないため、相手口座を自動決定できません。'],
                'source_resolution_type' => $sourceResolution['type'],
                'source_resolution_message' => $sourceResolution['message'],
                'destination_resolution_type' => $destinationResolution['type'],
                'destination_resolution_message' => $destinationResolution['message'],
                'unresolved_reason' => '振替方向を安全に特定できません。',
            ];
        }

        $sourceAccount = $accounts->first(
            fn (Account $account): bool => $account->id === $sourceAccountId,
        );
        $transferAccount = $accounts->first(
            fn (Account $account): bool => $account->id === $transferAccountId,
        );

        if (
            $sourceAccount instanceof Account
            && $transferAccount instanceof Account
            && $sourceAccount->currency !== $transferAccount->currency
        ) {
            return [
                'resolved_account_id' => $sourceAccountId,
                'resolved_transfer_account_id' => $transferAccountId,
                'validation_errors' => ['振替元口座と振替先口座は同じ通貨である必要があります。'],
                'source_resolution_type' => $sourceResolution['type'],
                'source_resolution_message' => $sourceResolution['message'],
                'destination_resolution_type' => $destinationResolution['type'],
                'destination_resolution_message' => $destinationResolution['message'],
                'unresolved_reason' => '振替元口座と振替先口座の通貨が一致しません。',
            ];
        }

        return [
            'resolved_account_id' => $sourceAccountId,
            'resolved_transfer_account_id' => $transferAccountId,
            'validation_errors' => $validationErrors,
            'source_resolution_type' => $sourceResolution['type'],
            'source_resolution_message' => $sourceResolution['message'],
            'destination_resolution_type' => $destinationResolution['type'],
            'destination_resolution_message' => $destinationResolution['message'],
            'unresolved_reason' => null,
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array{
     *     source_resolution_type:?string,
     *     source_resolution_message:?string,
     *     destination_resolution_type:?string,
     *     destination_resolution_message:?string,
     *     unresolved_reason:?string
     * }
     */
    public function explain(ImportRow $importRow, Collection $accounts): array
    {
        if ($importRow->detected_type !== 'transfer') {
            return [
                'source_resolution_type' => null,
                'source_resolution_message' => null,
                'destination_resolution_type' => null,
                'destination_resolution_message' => null,
                'unresolved_reason' => null,
            ];
        }

        $storedResolution = $importRow->transfer_resolution;

        if (! $this->hasStoredResolution($storedResolution)) {
            return $this->legacyExplain($importRow, $accounts);
        }

        return [
            'source_resolution_type' => is_array($storedResolution) ? ($storedResolution['source_resolution_type'] ?? null) : null,
            'source_resolution_message' => is_array($storedResolution) ? ($storedResolution['source_resolution_message'] ?? null) : null,
            'destination_resolution_type' => is_array($storedResolution) ? ($storedResolution['destination_resolution_type'] ?? null) : null,
            'destination_resolution_message' => is_array($storedResolution) ? ($storedResolution['destination_resolution_message'] ?? null) : null,
            'unresolved_reason' => is_array($storedResolution) ? ($storedResolution['unresolved_reason'] ?? null) : null,
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array{
     *     source_resolution_type:?string,
     *     source_resolution_message:?string,
     *     destination_resolution_type:?string,
     *     destination_resolution_message:?string,
     *     unresolved_reason:?string
     * }
     */
    private function legacyExplain(ImportRow $importRow, Collection $accounts): array
    {
        $sourceCandidates = $this->matchedSourceAccounts($importRow, $accounts);
        $sourceCandidate = $sourceCandidates->count() === 1 ? $sourceCandidates->first() : null;

        $sourceResolution = $sourceCandidate instanceof Account
            ? $this->sourceResolution($importRow, $sourceCandidate)
            : ($sourceCandidates->count() > 1
                ? [
                    'type' => 'ambiguous',
                    'message' => '振替元口座は口座名・取込用別名の候補が複数あり、自動決定できません。',
                ]
                : [
                    'type' => 'unresolved',
                    'message' => 'CSV の保有金融機関と一致する振替元口座を見つけられませんでした。',
                ]);

        $destinationResolution = [
            'type' => null,
            'message' => null,
        ];

        if ($sourceCandidate instanceof Account) {
            $manualTransferAccount = $this->manualTransferAccount($importRow, $accounts, $sourceCandidate);

            if ($manualTransferAccount instanceof Account) {
                $destinationResolution = $this->destinationResolution(
                    $importRow,
                    $sourceCandidate,
                    $manualTransferAccount,
                );
            } else {
                $matchedAccounts = $this->matchedAccounts($importRow, $accounts, $sourceCandidate);

                $destinationResolution = $matchedAccounts->count() === 1
                    ? $this->destinationResolution($importRow, $sourceCandidate, $matchedAccounts->first())
                    : ($matchedAccounts->count() > 1
                        ? [
                            'type' => 'ambiguous',
                            'message' => '振替先候補が複数あるため、自動決定できません。',
                        ]
                        : [
                            'type' => 'unresolved',
                            'message' => '摘要 / 説明 / メモから振替先口座を特定できませんでした。',
                        ]);
            }
        }

        return [
            'source_resolution_type' => $sourceResolution['type'],
            'source_resolution_message' => $sourceResolution['message'],
            'destination_resolution_type' => $destinationResolution['type'],
            'destination_resolution_message' => $destinationResolution['message'],
            'unresolved_reason' => $this->transferUnresolvedReason($importRow),
        ];
    }

    /**
     * @param  array<string, mixed>|mixed  $storedResolution
     */
    private function hasStoredResolution(mixed $storedResolution): bool
    {
        if (! is_array($storedResolution)) {
            return false;
        }

        foreach ([
            'source_resolution_type',
            'source_resolution_message',
            'destination_resolution_type',
            'destination_resolution_message',
            'unresolved_reason',
        ] as $key) {
            if (($storedResolution[$key] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    private function transferUnresolvedReason(ImportRow $importRow): ?string
    {
        $validationErrors = $importRow->validation_errors ?? [];

        if (! is_array($validationErrors)) {
            return null;
        }

        foreach ($validationErrors as $validationError) {
            if (! is_string($validationError)) {
                continue;
            }

            if (
                Str::contains($validationError, '振替元口座')
                || Str::contains($validationError, '振替先')
                || Str::contains($validationError, '相手口座')
                || Str::contains($validationError, '振替方向')
                || Str::contains($validationError, '同じ通貨')
            ) {
                return $validationError;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    private function matchedSourceAccounts(ImportRow $importRow, Collection $accounts): Collection
    {
        $normalizedAccountName = $this->normalizeText($importRow->account_name ?? '');

        if ($normalizedAccountName === '') {
            return collect();
        }

        return $accounts->filter(function (Account $account) use ($normalizedAccountName): bool {
            $candidates = collect([$account->name, ...($account->import_aliases ?? [])])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeText($value))
                ->unique();

            return $candidates->contains($normalizedAccountName);
        })->values();
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return Collection<int, Account>
     */
    private function matchedAccounts(ImportRow $importRow, Collection $accounts, Account $resolvedAccount): Collection
    {
        $haystacks = collect([
            $importRow->merchant_name,
            $importRow->description,
            is_string($importRow->raw_payload['メモ'] ?? null) ? $importRow->raw_payload['メモ'] : null,
        ])->filter(
            fn (?string $value): bool => is_string($value) && trim($value) !== '',
        )->map(
            fn (string $value): string => $this->normalizeText($value),
        );

        if ($haystacks->isEmpty()) {
            return collect();
        }

        $scoredAccounts = $accounts
            ->reject(fn (Account $account): bool => $account->id === $resolvedAccount->id)
            ->map(function (Account $account) use ($haystacks): array {
                $needles = collect([$account->name, ...($account->import_aliases ?? [])])
                    ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                    ->map(fn (string $value): string => $this->normalizeText($value))
                    ->unique()
                    ->values();

                if ($needles->isEmpty()) {
                    return [
                        'account' => $account,
                        'score' => 0,
                    ];
                }

                return [
                    'account' => $account,
                    'score' => $this->bestMatchScore($haystacks, $needles),
                ];
            })
            ->values();

        if ($scoredAccounts->isEmpty()) {
            return collect();
        }

        $bestScore = (int) $scoredAccounts->max('score');

        if ($bestScore <= 0) {
            return collect();
        }

        $bestMatches = $scoredAccounts
            ->filter(fn (array $candidate): bool => $candidate['score'] === $bestScore)
            ->map(fn (array $candidate): Account => $candidate['account'])
            ->values();

        if ($bestMatches->count() <= 1) {
            return $bestMatches;
        }

        $sameCurrencyMatches = $bestMatches
            ->filter(fn (Account $account): bool => $account->currency === $resolvedAccount->currency)
            ->values();

        if ($sameCurrencyMatches->count() === 1) {
            return $sameCurrencyMatches;
        }

        return $bestMatches;
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function manualTransferAccount(ImportRow $importRow, Collection $accounts, Account $resolvedAccount): ?Account
    {
        $manualTransferAccountId = $importRow->manual_resolved_transfer_account_id;

        if (! is_int($manualTransferAccountId)) {
            return null;
        }

        $matchedAccount = $accounts->first(
            fn (Account $account): bool => $account->id === $manualTransferAccountId && $account->id !== $resolvedAccount->id,
        );

        return $matchedAccount instanceof Account ? $matchedAccount : null;
    }

    /**
     * @param  Collection<int, string>  $haystacks
     * @param  Collection<int, string>  $needles
     */
    private function bestMatchScore(Collection $haystacks, Collection $needles): int
    {
        $bestScore = 0;

        foreach ($haystacks as $haystack) {
            foreach ($needles as $needle) {
                if ($needle === '') {
                    continue;
                }

                if ($haystack === $needle) {
                    $bestScore = max($bestScore, 3000 + mb_strlen($needle, 'UTF-8'));

                    continue;
                }

                if (Str::startsWith($haystack, $needle)) {
                    $bestScore = max($bestScore, 2000 + mb_strlen($needle, 'UTF-8'));

                    continue;
                }

                if (Str::contains($haystack, $needle)) {
                    $bestScore = max($bestScore, 1000 + mb_strlen($needle, 'UTF-8'));
                }
            }
        }

        return $bestScore;
    }

    /**
     * @return array{type:string, message:string}
     */
    private function sourceResolution(ImportRow $importRow, Account $resolvedAccount): array
    {
        $normalizedAccountName = $this->normalizeText($importRow->account_name ?? '');

        if ($normalizedAccountName === '') {
            return [
                'type' => 'unresolved',
                'message' => 'CSV の保有金融機関が空のため、振替元口座を判定できません。',
            ];
        }

        if ($this->normalizeText($resolvedAccount->name) === $normalizedAccountName) {
            return [
                'type' => 'account_name',
                'message' => '振替元口座は口座名一致で解決しました。',
            ];
        }

        return [
            'type' => 'account_alias',
            'message' => '振替元口座は取込用別名一致で解決しました。',
        ];
    }

    /**
     * @return array{type:string, message:string}
     */
    private function destinationResolution(ImportRow $importRow, Account $resolvedAccount, Account $matchedAccount): array
    {
        if ($importRow->manual_resolved_transfer_account_id === $matchedAccount->id) {
            return [
                'type' => 'manual',
                'message' => '振替先口座は手動指定で解決しました。',
            ];
        }

        $haystacks = collect([
            $importRow->merchant_name,
            $importRow->description,
            is_string($importRow->raw_payload['メモ'] ?? null) ? $importRow->raw_payload['メモ'] : null,
        ])->filter(
            fn (?string $value): bool => is_string($value) && trim($value) !== '',
        )->map(
            fn (string $value): string => $this->normalizeText($value),
        );

        $exactAccountName = $haystacks->contains($this->normalizeText($matchedAccount->name));
        if ($exactAccountName) {
            return [
                'type' => 'exact_account_name',
                'message' => '振替先口座は摘要 / 説明の口座名完全一致で解決しました。',
            ];
        }

        $exactAlias = collect($matchedAccount->import_aliases ?? [])
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => $this->normalizeText($value))
            ->contains(fn (string $alias): bool => $haystacks->contains($alias));

        if ($exactAlias) {
            return [
                'type' => 'exact_alias',
                'message' => '振替先口座は取込用別名の完全一致で解決しました。',
            ];
        }

        return [
            'type' => 'partial_match',
            'message' => '振替先口座は摘要 / 説明の部分一致で解決しました。',
        ];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveDirection(ImportRow $importRow, Account $resolvedAccount, Account $matchedAccount): array
    {
        $resolvedType = $resolvedAccount->type;
        $matchedType = $matchedAccount->type;

        if ($this->isTypePair($resolvedType, $matchedType, 'bank', 'credit_card')) {
            return $this->resolveFixedTypesBySignedAmount(
                $importRow,
                $resolvedAccount,
                $matchedAccount,
                negativeSourceType: 'bank',
                positiveSourceType: 'credit_card',
            );
        }

        if ($this->isTypePair($resolvedType, $matchedType, 'bank', 'e_money')) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        if ($this->isTypePair($resolvedType, $matchedType, 'point', 'e_money')) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        if ($this->isTypePair($resolvedType, $matchedType, 'bank', 'other')) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        if ($this->isTypePair($resolvedType, $matchedType, 'bank', 'bank')) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        if (
            $this->isTypePair($resolvedType, $matchedType, 'credit_card', 'e_money')
            || $this->isTypePair($resolvedType, $matchedType, 'credit_card', 'other')
        ) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        if (
            $this->isTypePair($resolvedType, $matchedType, 'bank', 'securities')
            || $this->isTypePair($resolvedType, $matchedType, 'credit_card', 'securities')
            || $this->isTypePair($resolvedType, $matchedType, 'e_money', 'securities')
            || $this->isTypePair($resolvedType, $matchedType, 'other', 'securities')
            || $this->isTypePair($resolvedType, $matchedType, 'point', 'securities')
        ) {
            return $this->resolveBySignedAmount($importRow, $resolvedAccount, $matchedAccount);
        }

        return [null, null];
    }

    private function isTypePair(string $left, string $right, string $expectedA, string $expectedB): bool
    {
        return ($left === $expectedA && $right === $expectedB)
            || ($left === $expectedB && $right === $expectedA);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveBySignedAmount(ImportRow $importRow, Account $resolvedAccount, Account $matchedAccount): array
    {
        $isNegative = $this->isNegativeAmountRow($importRow);

        if ($isNegative === null) {
            return [null, null];
        }

        return $isNegative
            ? [$resolvedAccount->id, $matchedAccount->id]
            : [$matchedAccount->id, $resolvedAccount->id];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function resolveFixedTypesBySignedAmount(
        ImportRow $importRow,
        Account $resolvedAccount,
        Account $matchedAccount,
        string $negativeSourceType,
        string $positiveSourceType,
    ): array {
        $isNegative = $this->isNegativeAmountRow($importRow);

        if ($isNegative === null) {
            return [null, null];
        }

        $sourceType = $isNegative ? $negativeSourceType : $positiveSourceType;

        if ($resolvedAccount->type === $sourceType) {
            return [$resolvedAccount->id, $matchedAccount->id];
        }

        if ($matchedAccount->type === $sourceType) {
            return [$matchedAccount->id, $resolvedAccount->id];
        }

        return [null, null];
    }

    private function isNegativeAmountRow(ImportRow $importRow): ?bool
    {
        foreach (['金額（円）', '金額(円)', '金額'] as $field) {
            $rawAmount = $importRow->raw_payload[$field] ?? null;

            if (! is_string($rawAmount)) {
                continue;
            }

            $normalized = str_replace([',', '円', '¥', ' '], '', trim($rawAmount));

            if ($normalized === '' || ! preg_match('/^[+-]?\d+(?:\.\d+)?$/', $normalized)) {
                continue;
            }

            return str_starts_with($normalized, '-');
        }

        return null;
    }

    private function normalizeText(string $value): string
    {
        $normalized = mb_convert_kana($value, 'asKV', 'UTF-8');
        $normalized = str_replace([' ', '　'], '', $normalized);

        return Str::lower(Str::squish($normalized));
    }
}
