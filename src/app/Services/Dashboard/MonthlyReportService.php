<?php

namespace App\Services\Dashboard;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class MonthlyReportService
{
    public function __construct(
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly CategoryExpenseSummaryService $categoryExpenseSummaryService,
        private readonly MonthlyClosingService $monthlyClosingService,
        private readonly DashboardPeriodService $dashboardPeriodService,
    ) {}

    /**
     * @param  array<int, array{currency: string, points: array<int, array{date: string, assets: string, liabilities: string, net_worth: string}>}>  $netWorthTrends
     * @param  array<int, array{id: int|null, name: string, currency: string, total_amount: string}>  $currentCategoryExpenses
     * @return array<string, mixed>
     */
    public function handle(
        User $user,
        CarbonImmutable $month,
        array $netWorthTrends,
        array $currentCategoryExpenses,
    ): array {
        $monthStart = $month->startOfMonth();
        $monthEnd = $month->endOfMonth();
        $transactions = $user->transactions()
            ->where('is_calculation_target', true)
            ->whereBetween('transaction_date', [
                $monthStart->toDateString(),
                $monthEnd->toDateString(),
            ])
            ->whereIn('type', ['income', 'expense'])
            ->get([
                'currency',
                'type',
                'amount',
                'merchant_name',
                'description',
                'category_id',
            ]);

        $quality = [
            'uncategorized_count' => $transactions
                ->whereNull('category_id')
                ->count(),
            'unconfirmed_count' => $user->transactions()
                ->whereBetween('transaction_date', [
                    $monthStart->toDateString(),
                    $monthEnd->toDateString(),
                ])
                ->where('is_confirmed', false)
                ->count(),
            'pending_import_count' => $user->imports()
                ->whereIn('status', ['uploaded', 'parsed', 'validated'])
                ->count(),
        ];

        return [
            'comparison_groups' => $this->buildComparisonGroups($user, $monthStart),
            'activity_groups' => $this->buildActivityGroups($transactions),
            'top_merchants' => $this->buildTopMerchants($transactions),
            'category_expense_groups' => $this->buildCategoryExpenseGroups(
                $currentCategoryExpenses,
                $this->categoryExpenseSummaryService->handle(
                    $user,
                    $monthStart->subMonthNoOverflow(),
                ),
                $monthStart->subMonthNoOverflow(),
            ),
            'quality' => $quality,
            'closing' => $this->monthlyClosingService->build($user, $monthStart, $quality),
            'net_worth_changes' => $this->buildNetWorthChanges($netWorthTrends),
        ];
    }

    /**
     * @param  array<int, array{id: int|null, name: string, currency: string, total_amount: string}>  $current
     * @param  array<int, array{id: int|null, name: string, currency: string, total_amount: string}>  $previous
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryExpenseGroups(
        array $current,
        array $previous,
        CarbonImmutable $previousMonth,
    ): array {
        $currentByCurrency = collect($current)->groupBy('currency');
        $previousByCurrency = collect($previous)->groupBy('currency');
        $currencies = $currentByCurrency->keys()
            ->merge($previousByCurrency->keys())
            ->unique()
            ->sort()
            ->values();

        return $currencies->map(function (string $currency) use (
            $currentByCurrency,
            $previousByCurrency,
            $previousMonth,
        ): array {
            $currentItems = $this->indexCategoryExpenses(
                $currentByCurrency->get($currency, collect()),
            );
            $previousItems = $this->indexCategoryExpenses(
                $previousByCurrency->get($currency, collect()),
            );
            $categoryKeys = collect(array_keys($currentItems))
                ->merge(array_keys($previousItems))
                ->unique();
            $currentTotal = collect($currentItems)
                ->sum(fn (array $item): int => $this->toMinorUnits($item['total_amount']));
            $previousTotal = collect($previousItems)
                ->sum(fn (array $item): int => $this->toMinorUnits($item['total_amount']));

            $items = $categoryKeys->map(function (string $key) use (
                $currentItems,
                $previousItems,
                $currentTotal,
            ): array {
                $currentItem = $currentItems[$key] ?? null;
                $previousItem = $previousItems[$key] ?? null;
                $currentAmount = $this->toMinorUnits($currentItem['total_amount'] ?? '0.00');
                $previousAmount = $this->toMinorUnits($previousItem['total_amount'] ?? '0.00');

                return [
                    'category_id' => $currentItem['id'] ?? $previousItem['id'] ?? null,
                    'category_name' => $currentItem['name']
                        ?? $previousItem['name']
                        ?? __('dashboard.report.uncategorized'),
                    'current_amount' => $this->formatMinorUnits($currentAmount),
                    'previous_amount' => $this->formatMinorUnits($previousAmount),
                    'change_amount' => $this->formatMinorUnits($currentAmount - $previousAmount),
                    'current_share_percent' => $currentTotal === 0
                        ? null
                        : number_format(($currentAmount / $currentTotal) * 100, 1, '.', ''),
                    '_current_minor_units' => $currentAmount,
                    '_change_minor_units' => $currentAmount - $previousAmount,
                ];
            })->sort(function (array $left, array $right): int {
                $currentComparison = $right['_current_minor_units'] <=> $left['_current_minor_units'];

                if ($currentComparison !== 0) {
                    return $currentComparison;
                }

                return abs($right['_change_minor_units']) <=> abs($left['_change_minor_units']);
            })->map(function (array $item): array {
                unset($item['_current_minor_units'], $item['_change_minor_units']);

                return $item;
            })->values()->all();

            return [
                'currency' => $currency,
                'previous_month_label' => $this->dashboardPeriodService
                    ->formatMonthLabel($previousMonth),
                'current_total' => $this->formatMinorUnits($currentTotal),
                'previous_total' => $this->formatMinorUnits($previousTotal),
                'change_amount' => $this->formatMinorUnits($currentTotal - $previousTotal),
                'items' => $items,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, array{id: int|null, name: string, currency: string, total_amount: string}>  $items
     * @return array<string, array{id: int|null, name: string, currency: string, total_amount: string}>
     */
    private function indexCategoryExpenses(Collection $items): array
    {
        return $items->mapWithKeys(fn (array $item): array => [
            $item['id'] === null ? 'uncategorized' : 'category:'.$item['id'] => $item,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildComparisonGroups(User $user, CarbonImmutable $month): array
    {
        $current = $this->indexSummaries($this->monthlySummaryService->handle($user, $month));
        $previousMonthDate = $month->subMonthNoOverflow();
        $previousYearDate = $month->subYearNoOverflow();
        $previousMonth = $this->indexSummaries(
            $this->monthlySummaryService->handle($user, $previousMonthDate),
        );
        $previousYear = $this->indexSummaries(
            $this->monthlySummaryService->handle($user, $previousYearDate),
        );

        $currencies = collect(array_keys($current))
            ->merge(array_keys($previousMonth))
            ->merge(array_keys($previousYear))
            ->unique()
            ->sort()
            ->values();

        return $currencies->map(function (string $currency) use (
            $current,
            $previousMonth,
            $previousMonthDate,
            $previousYear,
            $previousYearDate,
        ): array {
            $currentSummary = $current[$currency] ?? $this->emptySummary($currency);
            $previousMonthSummary = $previousMonth[$currency] ?? $this->emptySummary($currency);
            $previousYearSummary = $previousYear[$currency] ?? $this->emptySummary($currency);

            return [
                'currency' => $currency,
                'current' => $currentSummary,
                'previous_month' => $this->buildComparison(
                    $previousMonthDate,
                    $currentSummary,
                    $previousMonthSummary,
                ),
                'previous_year' => $this->buildComparison(
                    $previousYearDate,
                    $currentSummary,
                    $previousYearSummary,
                ),
            ];
        })->all();
    }

    /**
     * @param  Collection<int, mixed>  $transactions
     * @return array<int, array<string, int|string|null>>
     */
    private function buildActivityGroups(Collection $transactions): array
    {
        return $transactions
            ->groupBy('currency')
            ->sortKeys()
            ->map(function (Collection $currencyTransactions, string $currency): array {
                $expenses = $currencyTransactions->where('type', 'expense');
                $expenseMinorUnits = $expenses
                    ->sum(fn ($transaction): int => $this->toMinorUnits((string) $transaction->amount));
                $expenseCount = $expenses->count();

                return [
                    'currency' => $currency,
                    'transaction_count' => $currencyTransactions->count(),
                    'expense_count' => $expenseCount,
                    'average_expense' => $expenseCount === 0
                        ? null
                        : $this->formatMinorUnits((int) round($expenseMinorUnits / $expenseCount)),
                    'largest_expense' => $expenseCount === 0
                        ? null
                        : $this->formatMinorUnits((int) $expenses
                            ->max(fn ($transaction): int => $this->toMinorUnits((string) $transaction->amount))),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, mixed>  $transactions
     * @return array<int, array{currency: string, name: string, keyword: string|null, total_amount: string, transaction_count: int}>
     */
    private function buildTopMerchants(Collection $transactions): array
    {
        $unnamedMerchant = __('dashboard.report.unnamed_merchant');

        return $transactions
            ->where('type', 'expense')
            ->groupBy('currency')
            ->sortKeys()
            ->flatMap(function (Collection $currencyTransactions, string $currency) use ($unnamedMerchant): Collection {
                return $currencyTransactions
                    ->groupBy(function ($transaction) use ($unnamedMerchant): string {
                        $merchantName = trim((string) $transaction->merchant_name);

                        if ($merchantName !== '') {
                            return $merchantName;
                        }

                        $description = trim((string) $transaction->description);

                        return $description !== '' ? $description : $unnamedMerchant;
                    })
                    ->map(function (Collection $merchantTransactions, string $name) use ($currency, $unnamedMerchant): array {
                        $totalMinorUnits = $merchantTransactions
                            ->sum(fn ($transaction): int => $this->toMinorUnits((string) $transaction->amount));

                        return [
                            'currency' => $currency,
                            'name' => $name,
                            'keyword' => $name === $unnamedMerchant ? null : $name,
                            'total_amount' => $this->formatMinorUnits($totalMinorUnits),
                            'transaction_count' => $merchantTransactions->count(),
                            '_total_minor_units' => $totalMinorUnits,
                        ];
                    })
                    ->sortByDesc('_total_minor_units')
                    ->take(5)
                    ->map(function (array $item): array {
                        unset($item['_total_minor_units']);

                        return $item;
                    })
                    ->values();
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{currency: string, points: array<int, array{date: string, assets: string, liabilities: string, net_worth: string}>}>  $netWorthTrends
     * @return array<int, array<string, string|null>>
     */
    private function buildNetWorthChanges(array $netWorthTrends): array
    {
        return collect($netWorthTrends)->map(function (array $group): array {
            $first = $group['points'][0] ?? null;
            $last = $group['points'][count($group['points']) - 1] ?? null;
            $hasComparison = count($group['points']) >= 2;

            return [
                'currency' => $group['currency'],
                'start_date' => $first['date'] ?? null,
                'end_date' => $last['date'] ?? null,
                'start_net_worth' => $first['net_worth'] ?? null,
                'end_net_worth' => $last['net_worth'] ?? null,
                'change_amount' => $hasComparison
                    ? $this->formatMinorUnits(
                        $this->toMinorUnits((string) $last['net_worth'])
                        - $this->toMinorUnits((string) $first['net_worth']),
                    )
                    : null,
            ];
        })->all();
    }

    /**
     * @param  array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>  $summaries
     * @return array<string, array{currency: string, income_total: string, expense_total: string, balance_total: string}>
     */
    private function indexSummaries(array $summaries): array
    {
        return collect($summaries)->keyBy('currency')->all();
    }

    /**
     * @param  array{currency: string, income_total: string, expense_total: string, balance_total: string}  $current
     * @param  array{currency: string, income_total: string, expense_total: string, balance_total: string}  $comparison
     * @return array<string, string|null>
     */
    private function buildComparison(
        CarbonImmutable $period,
        array $current,
        array $comparison,
    ): array {
        $incomeChange = $this->toMinorUnits($current['income_total'])
            - $this->toMinorUnits($comparison['income_total']);
        $expenseChange = $this->toMinorUnits($current['expense_total'])
            - $this->toMinorUnits($comparison['expense_total']);
        $balanceChange = $this->toMinorUnits($current['balance_total'])
            - $this->toMinorUnits($comparison['balance_total']);

        return [
            'label' => $this->dashboardPeriodService->formatMonthLabel($period),
            'income_total' => $comparison['income_total'],
            'expense_total' => $comparison['expense_total'],
            'balance_total' => $comparison['balance_total'],
            'income_change_amount' => $this->formatMinorUnits($incomeChange),
            'income_change_percent' => $this->changePercent(
                $this->toMinorUnits($current['income_total']),
                $this->toMinorUnits($comparison['income_total']),
            ),
            'expense_change_amount' => $this->formatMinorUnits($expenseChange),
            'expense_change_percent' => $this->changePercent(
                $this->toMinorUnits($current['expense_total']),
                $this->toMinorUnits($comparison['expense_total']),
            ),
            'balance_change_amount' => $this->formatMinorUnits($balanceChange),
        ];
    }

    /**
     * @return array{currency: string, income_total: string, expense_total: string, balance_total: string}
     */
    private function emptySummary(string $currency): array
    {
        return [
            'currency' => $currency,
            'income_total' => '0.00',
            'expense_total' => '0.00',
            'balance_total' => '0.00',
        ];
    }

    private function changePercent(int $current, int $comparison): ?string
    {
        if ($comparison === 0) {
            return null;
        }

        return number_format((($current - $comparison) / abs($comparison)) * 100, 1, '.', '');
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
        $isNegative = $minorUnits < 0;
        $absolute = abs($minorUnits);

        return sprintf(
            '%s%d.%02d',
            $isNegative ? '-' : '',
            intdiv($absolute, 100),
            $absolute % 100,
        );
    }
}
