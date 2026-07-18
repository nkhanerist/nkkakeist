<?php

namespace App\Actions\Accounts;

use App\Models\AccountSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class UpdateAccountSnapshotAction
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(AccountSnapshot $snapshot, array $attributes): AccountSnapshot
    {
        $capturedAt = CarbonImmutable::parse(
            (string) $attributes['balance_date'],
            config('app.timezone'),
        )->endOfDay();

        $hasConflict = $snapshot->account->snapshots()
            ->where('purpose', 'valuation')
            ->whereKeyNot($snapshot->id)
            ->whereDate('captured_at', $capturedAt->toDateString())
            ->exists();

        if ($hasConflict) {
            throw ValidationException::withMessages([
                'balance_date' => '同じ日の評価額がすでにあります。既存の評価額を編集してください。',
            ]);
        }

        $snapshot->update([
            'captured_at' => $capturedAt,
            'balance' => $attributes['balance'],
            'source_name' => $attributes['source_name'],
            'note' => $attributes['note'],
        ]);

        return $snapshot->refresh();
    }
}
