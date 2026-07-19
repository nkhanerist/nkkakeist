<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MonthlyTrendService
{
    public function __construct(
        private readonly DashboardPeriodService $dashboardPeriodService,
    ) {}

    /**
     * @return array<int, array{
     *     month: string,
     *     label: string,
     *     summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>
     * }>
     */
    public function handle(User $user, CarbonImmutable $month, int $months = 6): array
    {
        $items = [];

        for ($offset = $months - 1; $offset >= 0; $offset--) {
            $targetMonth = $month->subMonths($offset);

            $items[] = [
                'month' => $targetMonth->format('Y-m'),
                'label' => $this->dashboardPeriodService->formatMonthLabel($targetMonth),
                'summaries' => $this->buildMonthlySummaries($user, $targetMonth),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>
     */
    private function buildMonthlySummaries(User $user, CarbonImmutable $month): array
    {
        $minorUnitExpression = $this->minorUnitExpression('amount');

        $rows = $user->transactions()
            ->where('is_calculation_target', true)
            ->whereBetween('transaction_date', [
                $month->startOfMonth()->toDateString(),
                $month->endOfMonth()->toDateString(),
            ])
            ->whereIn('type', ['income', 'expense'])
            ->groupBy('currency')
            ->orderBy('currency')
            ->selectRaw("
                currency,
                SUM(CASE WHEN type = 'income' THEN {$minorUnitExpression} ELSE 0 END) as income_minor_units,
                SUM(CASE WHEN type = 'expense' THEN {$minorUnitExpression} ELSE 0 END) as expense_minor_units
            ")
            ->get();

        return $rows->map(fn ($row): array => [
            'currency' => (string) $row->currency,
            'income_total' => $this->formatMinorUnits((int) $row->income_minor_units),
            'expense_total' => $this->formatMinorUnits((int) $row->expense_minor_units),
            'balance_total' => $this->formatMinorUnits(
                (int) $row->income_minor_units - (int) $row->expense_minor_units,
            ),
        ])->all();
    }

    private function formatMinorUnits(int $minorUnits): string
    {
        $isNegative = $minorUnits < 0;
        $absolute = abs($minorUnits);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return sprintf('%s%d.%s', $isNegative ? '-' : '', $whole, $fraction);
    }

    private function minorUnitExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            return sprintf('CAST(ROUND(%s * 100, 0) AS SIGNED)', $column);
        }

        return sprintf('CAST(ROUND(%s * 100, 0) AS INTEGER)', $column);
    }
}
