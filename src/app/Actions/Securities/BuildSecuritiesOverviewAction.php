<?php

namespace App\Actions\Securities;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use App\Services\Securities\SecuritiesPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BuildSecuritiesOverviewAction
{
    public function __construct(
        private readonly SecuritiesPeriodService $securitiesPeriodService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, string $requestedPeriod): array
    {
        $period = $this->securitiesPeriodService->resolve($requestedPeriod);
        $accounts = $user->accounts()
            ->where('type', 'securities')
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
        $accountIds = $accounts->pluck('id')->all();

        $snapshots = $this->accountSnapshots(
            $user,
            $accountIds,
            $period['start_date'],
            $period['end_date'],
        );
        $positions = $this->positionSnapshots(
            $user,
            $accountIds,
            $period['start_date'],
            $period['end_date'],
        );

        return [
            'selected_period' => $period['selected_period'],
            'period_options' => $period['period_options'],
            'period_label' => $period['period_label'],
            'accounts' => $accounts->map(
                fn (Account $account): array => $this->accountItem($account, $snapshots),
            )->values()->all(),
            'account_series' => $accounts->map(
                fn (Account $account): array => [
                    'key' => (string) $account->id,
                    'label' => $account->name,
                    'currency' => $account->currency,
                    'points' => ($snapshots[$account->id] ?? collect())
                        ->map(fn (AccountSnapshot $snapshot): array => [
                            'date' => $snapshot->captured_at->toDateString(),
                            'value' => (string) $snapshot->balance,
                        ])
                        ->values()
                        ->all(),
                ],
            )->values()->all(),
            'position_groups' => $accounts->map(
                fn (Account $account): array => [
                    'account_id' => $account->id,
                    'account_name' => $account->name,
                    'currency' => $account->currency,
                    'series' => $this->positionSeries($positions[$account->id] ?? collect()),
                ],
            )->values()->all(),
        ];
    }

    /**
     * @param  array<int, int>  $accountIds
     * @return Collection<int, Collection<int, AccountSnapshot>>
     */
    private function accountSnapshots(
        User $user,
        array $accountIds,
        ?CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Collection {
        return AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('account_id', $accountIds)
            ->where('purpose', 'valuation')
            ->when($startDate, fn ($query, CarbonImmutable $date) => $query
                ->whereDate('captured_at', '>=', $date->toDateString()))
            ->whereDate('captured_at', '<=', $endDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get(['id', 'account_id', 'captured_at', 'balance', 'source_name'])
            ->groupBy('account_id');
    }

    /**
     * @param  array<int, int>  $accountIds
     * @return Collection<int, Collection<int, InvestmentPositionSnapshot>>
     */
    private function positionSnapshots(
        User $user,
        array $accountIds,
        ?CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Collection {
        return InvestmentPositionSnapshot::query()
            ->where('user_id', $user->id)
            ->whereIn('account_id', $accountIds)
            ->when($startDate, fn ($query, CarbonImmutable $date) => $query
                ->whereDate('captured_at', '>=', $date->toDateString()))
            ->whereDate('captured_at', '<=', $endDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get([
                'id',
                'account_id',
                'captured_at',
                'position_key',
                'instrument_name',
                'valuation',
                'currency',
            ])
            ->groupBy('account_id');
    }

    /**
     * @param  Collection<int, Collection<int, AccountSnapshot>>  $snapshots
     * @return array<string, mixed>
     */
    private function accountItem(Account $account, Collection $snapshots): array
    {
        /** @var AccountSnapshot|null $latest */
        $latest = ($snapshots[$account->id] ?? collect())->last();

        return [
            'id' => $account->id,
            'name' => $account->name,
            'currency' => $account->currency,
            'latest_valuation' => $latest ? (string) $latest->balance : null,
            'latest_date' => $latest?->captured_at->toDateString(),
            'latest_source' => $latest?->source_name,
        ];
    }

    /**
     * @param  Collection<int, InvestmentPositionSnapshot>  $positions
     * @return array<int, array<string, mixed>>
     */
    private function positionSeries(Collection $positions): array
    {
        return $positions
            ->groupBy('position_key')
            ->map(function (Collection $items, string $positionKey): array {
                /** @var InvestmentPositionSnapshot $latest */
                $latest = $items->last();

                return [
                    'key' => $positionKey,
                    'label' => $latest->instrument_name,
                    'currency' => $latest->currency,
                    'points' => $items->map(fn (InvestmentPositionSnapshot $position): array => [
                        'date' => $position->captured_at->toDateString(),
                        'value' => (string) $position->valuation,
                    ])->values()->all(),
                ];
            })
            ->sortBy('label', SORT_NATURAL)
            ->values()
            ->all();
    }
}
