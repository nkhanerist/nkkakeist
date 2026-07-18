<?php

namespace App\Services\Dashboard;

use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;

class DailySnapshotStatusService
{
    /**
     * @return array{
     *     date: string,
     *     state: 'complete'|'partial'|'missing',
     *     account_count: int,
     *     position_count: int,
     *     asset_history_recorded: bool,
     *     last_imported_at: string|null
     * }
     */
    public function handle(User $user): array
    {
        $today = CarbonImmutable::today(config('app.timezone'));

        $accountCount = AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'Money Forward')
            ->whereDate('captured_at', $today)
            ->distinct()
            ->count('account_id');

        $positionCount = InvestmentPositionSnapshot::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'Money Forward')
            ->whereDate('captured_at', $today)
            ->count();

        $assetHistoryRecorded = AssetHistorySnapshot::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'money_forward')
            ->whereDate('captured_on', $today)
            ->exists();

        $state = match (true) {
            $accountCount > 0 && $assetHistoryRecorded => 'complete',
            $accountCount > 0 || $assetHistoryRecorded => 'partial',
            default => 'missing',
        };

        $lastImport = Import::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'balance_snapshot')
            ->where('status', 'imported')
            ->where('source_metadata->source', 'money_forward')
            ->latest('created_at')
            ->first();

        return [
            'date' => $today->toDateString(),
            'state' => $state,
            'account_count' => $accountCount,
            'position_count' => $positionCount,
            'asset_history_recorded' => $assetHistoryRecorded,
            'last_imported_at' => $lastImport?->created_at?->toIso8601String(),
        ];
    }
}
