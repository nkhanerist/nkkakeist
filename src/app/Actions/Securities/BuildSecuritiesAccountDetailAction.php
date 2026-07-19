<?php

namespace App\Actions\Securities;

use App\Models\Account;
use App\Models\AccountSnapshot;
use App\Models\InvestmentPositionSnapshot;
use App\Models\User;
use App\Services\Securities\SecuritiesPeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildSecuritiesAccountDetailAction
{
    public function __construct(
        private readonly SecuritiesPeriodService $securitiesPeriodService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(
        User $user,
        Account $account,
        string $requestedPeriod,
        ?string $requestedPositionKey,
    ): array {
        $period = $this->securitiesPeriodService->resolve($requestedPeriod);
        $snapshots = $this->snapshots($user, $account, $period['start_date'], $period['end_date']);
        $positions = $this->positions($user, $account, $period['start_date'], $period['end_date']);
        $positionHistories = $positions->groupBy('position_key');
        $latestSnapshot = $snapshots->last();
        $positionDate = $positions->max(
            fn (InvestmentPositionSnapshot $position): string => $position->captured_at->toDateString(),
        );
        $latestPositions = $positionDate === null
            ? collect()
            : $positions
                ->filter(fn (InvestmentPositionSnapshot $position): bool => $position->captured_at->toDateString() === $positionDate)
                ->groupBy('position_key')
                ->map(fn (Collection $items): InvestmentPositionSnapshot => $items->last())
                ->sortBy('instrument_name', SORT_NATURAL)
                ->values();
        $latestPositionsTotal = $latestPositions->sum(
            fn (InvestmentPositionSnapshot $position): int => $this->toMinorUnits((string) $position->valuation),
        );
        $selectedPositionKey = $requestedPositionKey !== null
            && $positionHistories->has($requestedPositionKey)
                ? $requestedPositionKey
                : null;

        return [
            'selected_period' => $period['selected_period'],
            'period_options' => $period['period_options'],
            'period_label' => $period['period_label'],
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'currency' => $account->currency,
                'latest_valuation' => $latestSnapshot ? (string) $latestSnapshot->balance : null,
                'latest_date' => $latestSnapshot?->captured_at->toDateString(),
                'latest_source' => $latestSnapshot?->source_name,
                'snapshot_count' => $snapshots->count(),
                'change_amount' => $snapshots->count() >= 2
                    ? $this->formatMinorUnits(
                        $this->toMinorUnits((string) $snapshots->last()->balance)
                        - $this->toMinorUnits((string) $snapshots->first()->balance),
                    )
                    : null,
            ],
            'account_series' => [
                'key' => (string) $account->id,
                'label' => $account->name,
                'currency' => $account->currency,
                'points' => $snapshots->map(fn (AccountSnapshot $snapshot): array => [
                    'date' => $snapshot->captured_at->toDateString(),
                    'value' => (string) $snapshot->balance,
                ])->values()->all(),
            ],
            'snapshots' => $this->snapshotRows($snapshots, $positions),
            'positions_as_of_date' => $positionDate,
            'latest_positions' => $latestPositions->map(
                fn (InvestmentPositionSnapshot $position): array => $this->latestPositionItem(
                    $position,
                    $positionHistories[$position->position_key] ?? collect(),
                    $latestPositionsTotal,
                ),
            )->values()->all(),
            'selected_position_key' => $selectedPositionKey,
            'selected_position' => $selectedPositionKey === null
                ? null
                : $this->positionDetail($positionHistories[$selectedPositionKey]),
        ];
    }

    /** @return Collection<int, AccountSnapshot> */
    private function snapshots(
        User $user,
        Account $account,
        ?CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Collection {
        return AccountSnapshot::query()
            ->where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->where('purpose', 'valuation')
            ->when($startDate, fn (Builder $query, CarbonImmutable $date): Builder => $query
                ->whereDate('captured_at', '>=', $date->toDateString()))
            ->whereDate('captured_at', '<=', $endDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get(['id', 'import_id', 'captured_at', 'balance', 'source_name']);
    }

    /** @return Collection<int, InvestmentPositionSnapshot> */
    private function positions(
        User $user,
        Account $account,
        ?CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): Collection {
        return InvestmentPositionSnapshot::query()
            ->where('user_id', $user->id)
            ->where('account_id', $account->id)
            ->when($startDate, fn (Builder $query, CarbonImmutable $date): Builder => $query
                ->whereDate('captured_at', '>=', $date->toDateString()))
            ->whereDate('captured_at', '<=', $endDate->toDateString())
            ->orderBy('captured_at')
            ->orderBy('id')
            ->get([
                'id',
                'account_snapshot_id',
                'import_id',
                'captured_at',
                'position_key',
                'instrument_name',
                'instrument_code',
                'asset_class',
                'quantity',
                'average_acquisition_price',
                'unit_price',
                'acquisition_cost',
                'valuation',
                'unrealized_gain',
                'currency',
                'source_name',
                'metadata',
            ]);
    }

    /**
     * @param  Collection<int, AccountSnapshot>  $snapshots
     * @param  Collection<int, InvestmentPositionSnapshot>  $positions
     * @return array<int, array<string, int|string|null>>
     */
    private function snapshotRows(Collection $snapshots, Collection $positions): array
    {
        $positionCounts = $positions->countBy('account_snapshot_id');

        return $snapshots->map(function (AccountSnapshot $snapshot, int $index) use (
            $snapshots,
            $positionCounts,
        ): array {
            /** @var AccountSnapshot|null $previous */
            $previous = $index > 0 ? $snapshots->get($index - 1) : null;

            return [
                'id' => $snapshot->id,
                'date' => $snapshot->captured_at->toDateString(),
                'valuation' => (string) $snapshot->balance,
                'change_amount' => $previous === null
                    ? null
                    : $this->formatMinorUnits(
                        $this->toMinorUnits((string) $snapshot->balance)
                        - $this->toMinorUnits((string) $previous->balance),
                    ),
                'source_name' => $snapshot->source_name,
                'import_id' => $snapshot->import_id,
                'position_count' => (int) ($positionCounts[$snapshot->id] ?? 0),
            ];
        })->reverse()->values()->all();
    }

    /**
     * @param  Collection<int, InvestmentPositionSnapshot>  $history
     * @return array<string, int|string|null>
     */
    private function latestPositionItem(
        InvestmentPositionSnapshot $position,
        Collection $history,
        int $positionsTotal,
    ): array {
        return [
            'position_key' => $position->position_key,
            'instrument_name' => $position->instrument_name,
            'instrument_code' => $position->instrument_code,
            'asset_class' => $position->asset_class,
            'valuation' => (string) $position->valuation,
            'acquisition_cost' => $this->acquisitionCost($position),
            'unrealized_gain' => $position->unrealized_gain === null ? null : (string) $position->unrealized_gain,
            'quantity' => $position->quantity === null ? null : (string) $position->quantity,
            'unit_price' => $position->unit_price === null ? null : (string) $position->unit_price,
            'currency' => $position->currency,
            'history_count' => $history->count(),
            'change_amount' => $history->count() >= 2
                ? $this->formatMinorUnits(
                    $this->toMinorUnits((string) $history->last()->valuation)
                    - $this->toMinorUnits((string) $history->first()->valuation),
                )
                : null,
            'share_percent' => $positionsTotal === 0
                ? null
                : number_format(
                    ($this->toMinorUnits((string) $position->valuation) / $positionsTotal) * 100,
                    1,
                    '.',
                    '',
                ),
        ];
    }

    /**
     * @param  Collection<int, InvestmentPositionSnapshot>  $history
     * @return array<string, mixed>
     */
    private function positionDetail(Collection $history): array
    {
        /** @var InvestmentPositionSnapshot $latest */
        $latest = $history->last();

        return [
            'position_key' => $latest->position_key,
            'instrument_name' => $latest->instrument_name,
            'instrument_code' => $latest->instrument_code,
            'asset_class' => $latest->asset_class,
            'currency' => $latest->currency,
            'latest' => [
                'date' => $latest->captured_at->toDateString(),
                'quantity' => $latest->quantity === null ? null : (string) $latest->quantity,
                'average_acquisition_price' => $latest->average_acquisition_price === null
                    ? null
                    : (string) $latest->average_acquisition_price,
                'unit_price' => $latest->unit_price === null ? null : (string) $latest->unit_price,
                'acquisition_cost' => $this->acquisitionCost($latest),
                'valuation' => (string) $latest->valuation,
                'unrealized_gain' => $latest->unrealized_gain === null ? null : (string) $latest->unrealized_gain,
                'source_name' => $latest->source_name,
            ],
            'series' => [
                'key' => $latest->position_key,
                'label' => $latest->instrument_name,
                'currency' => $latest->currency,
                'points' => $history->map(fn (InvestmentPositionSnapshot $position): array => [
                    'date' => $position->captured_at->toDateString(),
                    'value' => (string) $position->valuation,
                ])->values()->all(),
            ],
            'comparison_series' => [
                [
                    'key' => "{$latest->position_key}:valuation",
                    'label' => __('securities.series.valuation'),
                    'currency' => $latest->currency,
                    'color' => '#4f46e5',
                    'points' => $history->map(fn (InvestmentPositionSnapshot $position): array => [
                        'date' => $position->captured_at->toDateString(),
                        'value' => (string) $position->valuation,
                    ])->values()->all(),
                ],
                [
                    'key' => "{$latest->position_key}:acquisition-cost",
                    'label' => __('securities.series.acquisition_cost'),
                    'currency' => $latest->currency,
                    'color' => '#d97706',
                    'points' => $history
                        ->map(function (InvestmentPositionSnapshot $position): ?array {
                            $acquisitionCost = $this->acquisitionCost($position);

                            return $acquisitionCost === null
                                ? null
                                : [
                                    'date' => $position->captured_at->toDateString(),
                                    'value' => $acquisitionCost,
                                ];
                        })
                        ->filter()
                        ->values()
                        ->all(),
                ],
            ],
            'history' => $history->map(function (InvestmentPositionSnapshot $position, int $index) use (
                $history,
            ): array {
                /** @var InvestmentPositionSnapshot|null $previous */
                $previous = $index > 0 ? $history->get($index - 1) : null;

                return [
                    'date' => $position->captured_at->toDateString(),
                    'valuation' => (string) $position->valuation,
                    'change_amount' => $previous === null
                        ? null
                        : $this->formatMinorUnits(
                            $this->toMinorUnits((string) $position->valuation)
                            - $this->toMinorUnits((string) $previous->valuation),
                        ),
                    'quantity' => $position->quantity === null ? null : (string) $position->quantity,
                    'unit_price' => $position->unit_price === null ? null : (string) $position->unit_price,
                    'acquisition_cost' => $this->acquisitionCost($position),
                    'unrealized_gain' => $position->unrealized_gain === null
                        ? null
                        : (string) $position->unrealized_gain,
                    'source_name' => $position->source_name,
                ];
            })->reverse()->values()->all(),
        ];
    }

    private function acquisitionCost(InvestmentPositionSnapshot $position): ?string
    {
        if ($position->acquisition_cost !== null) {
            return (string) $position->acquisition_cost;
        }

        return isset($position->metadata['acquisition_cost'])
            ? (string) $position->metadata['acquisition_cost']
            : null;
    }

    private function toMinorUnits(string $amount): int
    {
        $normalized = trim($amount);
        $negative = str_starts_with($normalized, '-');
        $absolute = ltrim($normalized, '+-');
        [$whole, $fraction] = array_pad(explode('.', $absolute, 2), 2, '');
        $minorUnits = ((int) ($whole === '' ? '0' : $whole) * 100)
            + (int) str_pad(substr($fraction, 0, 2), 2, '0');

        return $negative ? -$minorUnits : $minorUnits;
    }

    private function formatMinorUnits(int $minorUnits): string
    {
        $absolute = abs($minorUnits);

        return sprintf(
            '%s%d.%02d',
            $minorUnits < 0 ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }
}
