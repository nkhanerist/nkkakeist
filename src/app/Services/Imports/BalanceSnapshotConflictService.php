<?php

namespace App\Services\Imports;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\Import;
use App\Models\ImportRow;

class BalanceSnapshotConflictService
{
    public function find(
        Import $import,
        ImportRow $importRow,
        ?Account $account = null,
    ): ?AccountSnapshot {
        $account ??= $importRow->resolvedAccount;
        $purpose = $this->purpose($importRow);
        $balanceDate = $importRow->transaction_date?->toDateString();

        if (
            ! $account instanceof Account
            || $account->user_id !== $import->user_id
            || $purpose === null
            || $balanceDate === null
        ) {
            return null;
        }

        return $account->snapshots()
            ->where('user_id', $import->user_id)
            ->where('purpose', $purpose)
            ->whereDate('captured_at', $balanceDate)
            ->orderByDesc('captured_at')
            ->orderByDesc('id')
            ->first();
    }

    public function purpose(ImportRow $importRow): ?string
    {
        $kind = $importRow->raw_payload['balance_kind'] ?? null;

        return match ($kind) {
            'valuation' => 'valuation',
            'account_balance', 'card_outstanding' => 'official_balance',
            default => null,
        };
    }
}
