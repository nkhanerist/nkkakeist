<?php

namespace App\Services\Dashboard;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\AssetHistorySnapshot;
use App\Models\Import;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DailySnapshotStatusService
{
    /**
     * @return array{
     *     date: string,
     *     state: 'complete'|'partial'|'missing',
     *     account_count: int,
     *     position_count: int,
     *     asset_history_recorded: bool,
     *     last_imported_at: string|null,
     *     required_account_count: int,
     *     updated_account_count: int,
     *     coverage_started_on: string|null,
     *     accounts: array<int, array{id: int, name: string, type: string, state: 'updated'|'stale', latest_snapshot_date: string}>,
     *     coverage_days: array<int, array{date: string, state: 'complete'|'partial'|'missing'|'not_required', updated_account_count: int, required_account_count: int, position_count: int, asset_history_recorded: bool}>,
     *     recent_failures: array<int, array{id: int, source_name: string, original_filename: string, failed_at: string, error_message: string|null}>
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

        $targetAccounts = $this->targetAccounts($user);
        $accounts = $targetAccounts
            ->map(function (Account $account) use ($today): array {
                $latestSnapshotAt = CarbonImmutable::parse($account->latest_snapshot_at)
                    ->setTimezone(config('app.timezone'));

                return [
                    'id' => $account->id,
                    'name' => $account->name,
                    'type' => $account->type,
                    'state' => $latestSnapshotAt->isSameDay($today)
                        ? 'updated'
                        : 'stale',
                    'latest_snapshot_date' => $latestSnapshotAt->toDateString(),
                ];
            })
            ->values();

        $updatedAccountCount = $accounts
            ->where('state', 'updated')
            ->count();
        $requiredAccountCount = $accounts->count();
        $coverageStartedOn = $targetAccounts
            ->map(fn (Account $account): string => CarbonImmutable::parse($account->first_snapshot_at)->toDateString())
            ->min();
        $allRequiredAccountsUpdated = $requiredAccountCount > 0
            && $updatedAccountCount === $requiredAccountCount;

        $state = match (true) {
            $allRequiredAccountsUpdated && $assetHistoryRecorded => 'complete',
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
            'required_account_count' => $requiredAccountCount,
            'updated_account_count' => $updatedAccountCount,
            'coverage_started_on' => $coverageStartedOn,
            'accounts' => $accounts->all(),
            'coverage_days' => $this->coverageDays($user, $today, $targetAccounts, $coverageStartedOn),
            'recent_failures' => $this->recentUnresolvedFailures($user, $today),
        ];
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, array{date: string, state: 'complete'|'partial'|'missing'|'not_required', updated_account_count: int, required_account_count: int, position_count: int, asset_history_recorded: bool}>
     */
    private function coverageDays(
        User $user,
        CarbonImmutable $today,
        Collection $accounts,
        ?string $coverageStartedOn,
    ): array {
        $start = $today->subDays(6)->startOfDay();
        $end = $today->endOfDay();
        $accountIds = $accounts->pluck('id');
        $accountStartDates = $accounts->mapWithKeys(fn (Account $account): array => [
            $account->id => CarbonImmutable::parse($account->first_snapshot_at)->toDateString(),
        ]);

        $accountCounts = $accountIds->isEmpty()
            ? collect()
            : AccountSnapshot::query()
                ->selectRaw('DATE(captured_at) as snapshot_date, COUNT(DISTINCT account_id) as snapshot_count')
                ->where('user_id', $user->id)
                ->where('source_name', 'Money Forward')
                ->whereIn('account_id', $accountIds)
                ->whereBetween('captured_at', [$start, $end])
                ->groupByRaw('DATE(captured_at)')
                ->get()
                ->mapWithKeys(fn ($row): array => [
                    CarbonImmutable::parse($row->snapshot_date)->toDateString() => (int) $row->snapshot_count,
                ]);

        $positionCounts = $accountIds->isEmpty()
            ? collect()
            : InvestmentPositionSnapshot::query()
                ->selectRaw('DATE(captured_at) as snapshot_date, COUNT(*) as position_count')
                ->where('user_id', $user->id)
                ->where('source_name', 'Money Forward')
                ->whereIn('account_id', $accountIds)
                ->whereBetween('captured_at', [$start, $end])
                ->groupByRaw('DATE(captured_at)')
                ->get()
                ->mapWithKeys(fn ($row): array => [
                    CarbonImmutable::parse($row->snapshot_date)->toDateString() => (int) $row->position_count,
                ]);

        $assetHistoryDates = AssetHistorySnapshot::query()
            ->where('user_id', $user->id)
            ->where('source_name', 'money_forward')
            ->whereDate('captured_on', '>=', $start->toDateString())
            ->whereDate('captured_on', '<=', $end->toDateString())
            ->get(['captured_on'])
            ->mapWithKeys(fn (AssetHistorySnapshot $snapshot): array => [
                $snapshot->captured_on->toDateString() => true,
            ]);

        return collect(range(6, 0))
            ->map(function (int $daysAgo) use (
                $today,
                $accountCounts,
                $positionCounts,
                $assetHistoryDates,
                $accountStartDates,
                $coverageStartedOn,
            ): array {
                $date = $today->subDays($daysAgo)->toDateString();
                $updatedAccountCount = (int) ($accountCounts[$date] ?? 0);
                $positionCount = (int) ($positionCounts[$date] ?? 0);
                $assetHistoryRecorded = (bool) ($assetHistoryDates[$date] ?? false);
                $requiredAccountCount = $accountStartDates
                    ->filter(fn (string $startDate): bool => $startDate <= $date)
                    ->count();
                $allRequiredAccountsUpdated = $requiredAccountCount > 0
                    && $updatedAccountCount === $requiredAccountCount;
                $state = match (true) {
                    $coverageStartedOn === null || $date < $coverageStartedOn => 'not_required',
                    $allRequiredAccountsUpdated && $assetHistoryRecorded => 'complete',
                    $updatedAccountCount > 0 || $positionCount > 0 || $assetHistoryRecorded => 'partial',
                    default => 'missing',
                };

                return [
                    'date' => $date,
                    'state' => $state,
                    'updated_account_count' => $updatedAccountCount,
                    'required_account_count' => $requiredAccountCount,
                    'position_count' => $positionCount,
                    'asset_history_recorded' => $assetHistoryRecorded,
                ];
            })
            ->all();
    }

    /**
     * @return Collection<int, Account>
     */
    private function targetAccounts(User $user): Collection
    {
        return $user->accounts()
            ->where('is_active', true)
            ->where('include_in_net_worth', true)
            ->whereIn('type', ['bank', 'credit_card', 'securities'])
            ->whereHas('snapshots', fn ($query) => $query
                ->where('source_name', 'Money Forward'))
            ->withMax([
                'snapshots as latest_snapshot_at' => fn ($query) => $query
                    ->where('source_name', 'Money Forward'),
            ], 'captured_at')
            ->withMin([
                'snapshots as first_snapshot_at' => fn ($query) => $query
                    ->where('source_name', 'Money Forward'),
            ], 'captured_at')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Each source is actionable only when its latest attempt failed. A later
     * successful or preview-ready import therefore clears the warning.
     *
     * @return array<int, array{id: int, source_name: string, original_filename: string, failed_at: string, error_message: string|null}>
     */
    private function recentUnresolvedFailures(User $user, CarbonImmutable $today): array
    {
        return Import::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', $today->subDays(6)->startOfDay())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->unique('source_name')
            ->where('status', 'failed')
            ->take(3)
            ->map(fn (Import $import): array => [
                'id' => $import->id,
                'source_name' => $import->source_name,
                'original_filename' => $import->original_filename,
                'failed_at' => $import->created_at->toIso8601String(),
                'error_message' => $import->error_message,
            ])
            ->values()
            ->all();
    }
}
