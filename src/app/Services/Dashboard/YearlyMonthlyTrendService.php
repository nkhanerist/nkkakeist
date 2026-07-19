<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class YearlyMonthlyTrendService
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
    public function handle(User $user, CarbonImmutable $year): array
    {
        $currencies = $this->yearCurrencies($user, $year);
        $items = [];

        for ($month = 1; $month <= 12; $month++) {
            $targetMonth = $year->setMonth($month)->startOfMonth();
            $items[] = [
                'month' => $targetMonth->format('Y-m'),
                'label' => $this->dashboardPeriodService->formatMonthLabel($targetMonth),
                'summaries' => $this->buildMonthlySummaries($user, $targetMonth, $currencies),
            ];
        }

        return $items;
    }

    /**
     * 指定されたユーザーの指定された年に関連する通貨のリストを取得します。
     * トランザクションとアカウントの両方を考慮し、重複しないようにユニークな通貨をソートされた状態で返します。
     *
     * @param  User  $user  対象のユーザー。
     * @param  CarbonImmutable  $year  対象の年を表すCarbonImmutableオブジェクト。
     * @return array 指定された年に関連する通貨の文字列の配列。
     */
    private function yearCurrencies(User $user, CarbonImmutable $year): array
    {
        $transactionCurrencies = $user->transactions()
            ->where('is_calculation_target', true)
            ->whereBetween('transaction_date', [
                $year->startOfYear()->toDateString(),
                $year->endOfYear()->toDateString(),
            ])
            ->whereIn('type', ['income', 'expense'])
            ->orderBy('currency')
            ->distinct()
            ->pluck('currency')
            ->map(fn ($currency): string => (string) $currency)
            ->values()
            ->all();

        $accountCurrencies = $user->accounts()
            ->where('created_at', '<=', $year->endOfYear())
            ->orderBy('currency')
            ->distinct()
            ->pluck('currency')
            ->map(fn ($currency): string => (string) $currency)
            ->values()
            ->all();

        return collect(array_merge($transactionCurrencies, $accountCurrencies))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $currencies
     * @return array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>
     */
    private function buildMonthlySummaries(User $user, CarbonImmutable $month, array $currencies): array
    {
        if ($currencies === []) {
            return [];
        }

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
            ->get()
            ->keyBy(fn ($row): string => (string) $row->currency);

        return collect($currencies)->map(function (string $currency) use ($rows): array {
            $row = $rows->get($currency);
            $incomeMinorUnits = $row === null ? 0 : (int) $row->income_minor_units;
            $expenseMinorUnits = $row === null ? 0 : (int) $row->expense_minor_units;

            return [
                'currency' => $currency,
                'income_total' => $this->formatMinorUnits($incomeMinorUnits),
                'expense_total' => $this->formatMinorUnits($expenseMinorUnits),
                'balance_total' => $this->formatMinorUnits($incomeMinorUnits - $expenseMinorUnits),
            ];
        })->all();
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
