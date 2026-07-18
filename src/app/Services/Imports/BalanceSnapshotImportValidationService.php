<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\Import;
use App\Models\ImportRow;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BalanceSnapshotImportValidationService
{
    public function __construct(
        private readonly BalanceSnapshotConflictService $conflictService,
    ) {}

    public function handle(Import $import): Import
    {
        $import->loadMissing('user', 'importRows');
        $accounts = $import->user->accounts()
            ->where('is_active', true)
            ->get();
        $duplicateRows = 0;

        DB::transaction(function () use ($import, $accounts, &$duplicateRows): void {
            $seenHashes = [];

            foreach ($import->importRows as $importRow) {
                $resolvedAccount = $this->resolveAccount($importRow, $accounts);
                $errors = $this->validationErrors($importRow, $resolvedAccount);
                $purpose = $this->conflictService->purpose($importRow);
                $duplicateHash = $resolvedAccount === null || $purpose === null
                    ? null
                    : $this->duplicateHash($import, $importRow, $resolvedAccount, $purpose);
                $isExistingDuplicate = $duplicateHash !== null
                    && $resolvedAccount?->snapshots()
                        ->where('duplicate_hash', $duplicateHash)
                        ->exists();
                $isFileDuplicate = $duplicateHash !== null && isset($seenHashes[$duplicateHash]);
                $isDuplicate = $isExistingDuplicate || $isFileDuplicate;
                $sameDaySnapshot = $resolvedAccount === null || $purpose === null
                    ? null
                    : $this->conflictService->find($import, $importRow, $resolvedAccount);
                $replaceSnapshotId = null;

                if (
                    ! $isDuplicate
                    && $errors === []
                    && $sameDaySnapshot !== null
                ) {
                    if ($importRow->replace_account_snapshot_id === $sameDaySnapshot->id) {
                        $replaceSnapshotId = $sameDaySnapshot->id;
                    } else {
                        $errors[] = '同じ日の残高がすでにあります。既存値を確認してから取り込んでください。';
                    }
                }

                if ($duplicateHash !== null) {
                    $seenHashes[$duplicateHash] = true;
                }

                if ($isDuplicate) {
                    $duplicateRows++;
                }

                $importRow->update([
                    'resolved_account_id' => $resolvedAccount?->id,
                    'replace_account_snapshot_id' => $replaceSnapshotId,
                    'resolved_transfer_account_id' => null,
                    'resolved_category_id' => null,
                    'resolved_subcategory_id' => null,
                    'resolved_is_calculation_target' => false,
                    'resolved_affects_account_balance' => false,
                    'duplicate_hash' => $duplicateHash,
                    'is_duplicate_candidate' => $isDuplicate,
                    'validation_errors' => $errors,
                    'status' => $errors === [] ? 'ready' : 'error',
                ]);
            }

            $import->update([
                'status' => 'validated',
                'duplicate_rows' => $duplicateRows,
                'error_message' => null,
            ]);
        });

        return $import->fresh([
            'account',
            'importRows.resolvedAccount',
            'importRows.manualResolvedAccount',
        ]);
    }

    /**
     * @param  Collection<int, Account>  $accounts
     */
    private function resolveAccount(ImportRow $importRow, Collection $accounts): ?Account
    {
        if ($importRow->manual_resolved_account_id !== null) {
            return $accounts->firstWhere('id', $importRow->manual_resolved_account_id);
        }

        $accountName = $this->normalizeText((string) $importRow->account_name);
        $matches = $accounts->filter(function (Account $account) use ($accountName): bool {
            return collect([$account->name, ...($account->import_aliases ?? [])])
                ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
                ->map(fn (string $value): string => $this->normalizeText($value))
                ->contains($accountName);
        })->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * @return array<int, string>
     */
    private function validationErrors(ImportRow $importRow, ?Account $account): array
    {
        $errors = [];
        $kind = $this->kind($importRow);
        $currency = strtoupper((string) ($importRow->raw_payload['currency'] ?? ''));

        if ($account === null) {
            $errors[] = '取込先口座を1件に特定できません。口座を選択してください。';

            return $errors;
        }

        if ($account->currency !== $currency) {
            $errors[] = '取得データと取込先口座の通貨が一致しません。';
        }

        if ($kind === 'valuation' && $account->balance_method !== 'snapshot') {
            $errors[] = '時価評価額は評価額スナップショット方式の口座へ取り込んでください。';
        }

        if ($kind === 'card_outstanding' && $account->balance_role !== 'liability') {
            $errors[] = 'カード未払残高は負債口座へ取り込んでください。';
        }

        if (
            $kind === 'account_balance'
            && ($account->balance_method !== 'ledger' || $account->balance_role === 'clearing')
        ) {
            $errors[] = '公式口座残高は台帳方式の資産・負債口座へ取り込んでください。';
        }

        if ($kind === null) {
            $errors[] = '残高種別を解釈できません。';
        }

        return $errors;
    }

    private function kind(ImportRow $importRow): ?string
    {
        $kind = $importRow->raw_payload['balance_kind'] ?? null;

        return is_string($kind) ? $kind : null;
    }

    private function duplicateHash(
        Import $import,
        ImportRow $importRow,
        Account $account,
        string $purpose,
    ): string {
        return hash('sha256', implode('|', [
            $import->user_id,
            $account->id,
            $purpose,
            $this->kind($importRow),
            $importRow->transaction_date?->toDateString(),
            (string) $importRow->amount,
            (string) ($import->source_metadata['source'] ?? ''),
        ]));
    }

    private function normalizeText(string $value): string
    {
        return mb_strtolower(trim(mb_convert_kana($value, 'asKV', 'UTF-8')), 'UTF-8');
    }
}
