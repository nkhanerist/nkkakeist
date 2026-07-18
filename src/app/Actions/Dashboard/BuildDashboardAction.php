<?php

namespace App\Actions\Dashboard;

use App\Models\User;
use App\Services\Dashboard\AccountBalanceSummaryService;
use App\Services\Dashboard\CategoryExpenseSummaryService;
use App\Services\Dashboard\DashboardPeriodService;
use App\Services\Dashboard\MonthlySummaryService;
use App\Services\Dashboard\MonthlyTrendService;
use App\Services\Dashboard\YearlyCategoryExpenseSummaryService;
use App\Services\Dashboard\YearlyMonthlyTrendService;
use App\Services\Dashboard\YearlySummaryService;

class BuildDashboardAction
{
    public function __construct(
        private readonly DashboardPeriodService $dashboardPeriodService,
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly AccountBalanceSummaryService $accountBalanceSummaryService,
        private readonly CategoryExpenseSummaryService $categoryExpenseSummaryService,
        private readonly MonthlyTrendService $monthlyTrendService,
        private readonly YearlySummaryService $yearlySummaryService,
        private readonly YearlyCategoryExpenseSummaryService $yearlyCategoryExpenseSummaryService,
        private readonly YearlyMonthlyTrendService $yearlyMonthlyTrendService,
    ) {}

    /**
     * @param  array<string, mixed>  $query
     * @return array{
     *     selected_view: 'month'|'year',
     *     selected_month: string,
     *     selected_year: string,
     *     selected_period_label: string,
     *     year_options: array<int, array{value: string, label: string}>,
     *     month_options: array<int, array{value: string, label: string}>,
     *     year_view_ready: bool,
     *     monthly_summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>,
     *     account_summaries: array<int, array{id: int, name: string, type: string, currency: string, initial_balance: string, current_balance: string, is_active: bool}>,
     *     category_expenses: array<int, array{id: int|null, name: string, currency: string, total_amount: string}>,
     *     monthly_trends: array<int, array{month: string, label: string, summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>}>,
     *     yearly_summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>,
     *     yearly_category_expenses: array<int, array{currency: string, items: array<int, array{category_id: int|null, category_name: string, total_amount: string}>}>,
     *     yearly_trends: array<int, array{month: string, label: string, summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>}>
     * }
     */
    public function handle(User $user, array $query): array
    {
        $period = $this->dashboardPeriodService->resolve($query);

        if ($period['selected_view'] === 'year') {
            return [
                'selected_view' => $period['selected_view'],
                'selected_month' => $period['selected_month'],
                'selected_year' => $period['selected_year'],
                'selected_period_label' => $period['selected_period_label'],
                'year_options' => $period['year_options'],
                'month_options' => $period['month_options'],
                'year_view_ready' => true,
                'monthly_summaries' => [],
                'account_summaries' => [],
                'category_expenses' => [],
                'monthly_trends' => [],
                'yearly_summaries' => $this->yearlySummaryService->handle($user, $period['year_start']),
                'yearly_category_expenses' => $this->yearlyCategoryExpenseSummaryService->handle($user, $period['year_start']),
                'yearly_trends' => $this->yearlyMonthlyTrendService->handle($user, $period['year_start']),
            ];
        }

        return [
            'selected_view' => $period['selected_view'],
            'selected_month' => $period['selected_month'],
            'selected_year' => $period['selected_year'],
            'selected_period_label' => $period['selected_period_label'],
            'year_options' => $period['year_options'],
            'month_options' => $period['month_options'],
            'year_view_ready' => true,
            'monthly_summaries' => $this->monthlySummaryService->handle($user, $period['month_start']),
            'account_summaries' => $this->accountBalanceSummaryService->handle($user, $period['month_start']),
            'category_expenses' => $this->categoryExpenseSummaryService->handle($user, $period['month_start']),
            'monthly_trends' => $this->monthlyTrendService->handle($user, $period['month_start']),
            'yearly_summaries' => [],
            'yearly_category_expenses' => [],
            'yearly_trends' => [],
        ];
    }
}
