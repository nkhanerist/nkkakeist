<?php

namespace App\Services\Securities;

use Carbon\CarbonImmutable;

class SecuritiesPeriodService
{
    private const PERIODS = [
        '30d' => ['days' => 30],
        '90d' => ['days' => 90],
        '1y' => ['days' => 365],
        'all' => ['days' => null],
    ];

    /**
     * @return array{
     *     selected_period: string,
     *     period_label: string,
     *     period_options: array<int, array{value: string, label: string}>,
     *     start_date: CarbonImmutable|null,
     *     end_date: CarbonImmutable
     * }
     */
    public function resolve(string $requestedPeriod): array
    {
        $selectedPeriod = array_key_exists($requestedPeriod, self::PERIODS)
            ? $requestedPeriod
            : '90d';
        $today = CarbonImmutable::today(config('app.timezone'));
        $days = self::PERIODS[$selectedPeriod]['days'];

        return [
            'selected_period' => $selectedPeriod,
            'period_label' => __("securities.periods.{$selectedPeriod}"),
            'period_options' => collect(self::PERIODS)
                ->map(fn (array $period, string $value): array => [
                    'value' => $value,
                    'label' => __("securities.periods.{$value}"),
                ])
                ->values()
                ->all(),
            'start_date' => is_int($days) ? $today->subDays($days - 1) : null,
            'end_date' => $today,
        ];
    }
}
