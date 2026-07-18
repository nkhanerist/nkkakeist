<?php

namespace App\Services\Dashboard;

use Carbon\CarbonImmutable;

class DashboardPeriodService
{
    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     selected_view: 'month'|'year',
     *     selected_month: string,
     *     selected_year: string,
     *     selected_period_label: string,
     *     month_start: CarbonImmutable,
     *     month_end: CarbonImmutable,
     *     year_start: CarbonImmutable,
     *     year_end: CarbonImmutable,
     *     year_options: array<int, array{value: string, label: string}>,
     *     month_options: array<int, array{value: string, label: string}>
     * }
     */
    public function resolve(array $query): array
    {
        $now = CarbonImmutable::now();
        $selectedView = $this->resolveView($query['view'] ?? null);
        $selectedMonth = $this->resolveMonth($query['month'] ?? null, $now);
        $selectedYear = $this->resolveYear($query['year'] ?? null, $selectedMonth, $now);

        $monthStart = $selectedMonth->startOfMonth();
        $yearStart = $selectedYear->startOfYear();

        return [
            'selected_view' => $selectedView,
            'selected_month' => $monthStart->format('Y-m'),
            'selected_year' => $yearStart->format('Y'),
            'selected_period_label' => $selectedView === 'year'
                ? $yearStart->isoFormat('YYYY年')
                : $monthStart->isoFormat('YYYY年M月'),
            'month_start' => $monthStart,
            'month_end' => $monthStart->endOfMonth(),
            'year_start' => $yearStart,
            'year_end' => $yearStart->endOfYear(),
            'year_options' => $this->yearOptions($selectedYear, $now),
            'month_options' => $this->monthOptions(),
        ];
    }

    private function resolveView(mixed $view): string
    {
        $normalized = is_string($view) ? trim($view) : null;

        return in_array($normalized, ['month', 'year'], true) ? $normalized : 'month';
    }

    private function resolveMonth(mixed $month, CarbonImmutable $now): CarbonImmutable
    {
        $normalized = is_string($month) ? trim($month) : null;

        if ($normalized === null || ! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $normalized)) {
            return $now->startOfMonth();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $normalized)->startOfMonth();
        } catch (\Throwable) {
            return $now->startOfMonth();
        }
    }

    private function resolveYear(mixed $year, CarbonImmutable $selectedMonth, CarbonImmutable $now): CarbonImmutable
    {
        $normalized = is_string($year) ? trim($year) : null;

        if ($normalized !== null && preg_match('/^\d{4}$/', $normalized)) {
            try {
                return CarbonImmutable::create((int) $normalized, 1, 1, 0, 0, 0, $now->timezone)
                    ->startOfYear();
            } catch (\Throwable) {
                return $now->startOfYear();
            }
        }

        return $selectedMonth->startOfYear();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function yearOptions(CarbonImmutable $selectedYear, CarbonImmutable $now): array
    {
        $selected = (int) $selectedYear->format('Y');
        $current = (int) $now->format('Y');
        $start = min($selected, $current) - 5;
        $end = max($selected, $current) + 1;
        $items = [];

        for ($year = $end; $year >= $start; $year--) {
            $items[] = [
                'value' => (string) $year,
                'label' => sprintf('%d年', $year),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function monthOptions(): array
    {
        $items = [];

        for ($month = 1; $month <= 12; $month++) {
            $items[] = [
                'value' => str_pad((string) $month, 2, '0', STR_PAD_LEFT),
                'label' => sprintf('%d月', $month),
            ];
        }

        return $items;
    }
}
