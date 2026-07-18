<?php

namespace App\Actions\Accounts;

use App\Models\Account;
use App\Models\AccountSnapshot;
use Carbon\CarbonImmutable;

class StoreAccountSnapshotAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Account $account, array $attributes): AccountSnapshot
    {
        $capturedAt = CarbonImmutable::parse(
            (string) $attributes['balance_date'],
            config('app.timezone'),
        )->endOfDay();

        $snapshot = $account->snapshots()
            ->where('purpose', 'valuation')
            ->whereDate('captured_at', $capturedAt->toDateString())
            ->first();

        $values = [
            'user_id' => $account->user_id,
            'captured_at' => $capturedAt,
            'purpose' => 'valuation',
            'balance' => $attributes['balance'],
            'source_name' => $attributes['source_name'],
            'note' => $attributes['note'],
        ];

        if ($snapshot !== null) {
            $snapshot->update($values);

            return $snapshot->refresh();
        }

        return $account->snapshots()->create($values);
    }
}
