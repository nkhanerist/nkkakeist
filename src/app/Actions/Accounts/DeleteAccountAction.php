<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\ImportRow;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

class DeleteAccountAction
{
    public function handle(Account $account): void
    {
        if (
            Transaction::withTrashed()->where('account_id', $account->id)->exists()
            || Transaction::withTrashed()->where('transfer_account_id', $account->id)->exists()
            || ImportRow::query()
                ->where('resolved_account_id', $account->id)
                ->whereHas('import', fn ($query) => $query
                    ->where('user_id', $account->user_id)
                    ->where('status', '!=', 'imported'))
                ->exists()
            || ImportRow::query()
                ->where('resolved_transfer_account_id', $account->id)
                ->whereHas('import', fn ($query) => $query
                    ->where('user_id', $account->user_id)
                    ->where('status', '!=', 'imported'))
                ->exists()
            || ImportRow::query()
                ->where('manual_resolved_transfer_account_id', $account->id)
                ->whereHas('import', fn ($query) => $query
                    ->where('user_id', $account->user_id)
                    ->where('status', '!=', 'imported'))
                ->exists()
            || $account->imports()->exists()
            || $account->snapshots()->exists()
        ) {
            throw ValidationException::withMessages([
                'account' => '取引または取込履歴に紐づく口座は削除できません。必要な場合は無効化してください。',
            ]);
        }

        $account->delete();
    }
}
