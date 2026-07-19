<?php

namespace App\Actions\Dashboard;

use App\Models\User;
use App\Services\Dashboard\AccountBalanceSummaryService;
use App\Services\Dashboard\AssetHistoryTrendService;
use App\Services\Dashboard\CategoryExpenseSummaryService;
use App\Services\Dashboard\DailySnapshotStatusService;
use App\Services\Dashboard\DashboardPeriodService;
use App\Services\Dashboard\MonthlyReportService;
use App\Services\Dashboard\MonthlySummaryService;
use App\Services\Dashboard\MonthlyTrendService;
use App\Services\Dashboard\NetWorthTrendService;
use App\Services\Dashboard\WeeklyImportStatusService;
use App\Services\Dashboard\YearlyCategoryExpenseSummaryService;
use App\Services\Dashboard\YearlyMonthlyTrendService;
use App\Services\Dashboard\YearlySummaryService;

class BuildDashboardAction
{
    public function __construct(
        private readonly DashboardPeriodService $dashboardPeriodService,
        private readonly MonthlySummaryService $monthlySummaryService,
        private readonly MonthlyReportService $monthlyReportService,
        private readonly AccountBalanceSummaryService $accountBalanceSummaryService,
        private readonly CategoryExpenseSummaryService $categoryExpenseSummaryService,
        private readonly MonthlyTrendService $monthlyTrendService,
        private readonly YearlySummaryService $yearlySummaryService,
        private readonly YearlyCategoryExpenseSummaryService $yearlyCategoryExpenseSummaryService,
        private readonly YearlyMonthlyTrendService $yearlyMonthlyTrendService,
        private readonly NetWorthTrendService $netWorthTrendService,
        private readonly AssetHistoryTrendService $assetHistoryTrendService,
        private readonly DailySnapshotStatusService $dailySnapshotStatusService,
        private readonly WeeklyImportStatusService $weeklyImportStatusService,
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
     *     monthly_report: array<string, mixed>|null,
     *     account_summaries: array<int, array{id: int, name: string, type: string, currency: string, initial_balance: string, current_balance: string, is_active: bool}>,
     *     category_expenses: array<int, array{id: int|null, name: string, currency: string, total_amount: string}>,
     *     monthly_trends: array<int, array{month: string, label: string, summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>}>,
     *     yearly_summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>,
     *     yearly_category_expenses: array<int, array{currency: string, items: array<int, array{category_id: int|null, category_name: string, total_amount: string}>}>,
     *     yearly_trends: array<int, array{month: string, label: string, summaries: array<int, array{currency: string, income_total: string, expense_total: string, balance_total: string}>}>,
     *     net_worth_trends: array<int, array{currency: string, points: array<int, array{date: string, assets: string, liabilities: string, net_worth: string}>}>,
     *     asset_history_trends: array<int, array{currency: string, source_name: string, points: array<int, array{date: string, total_assets: string, breakdown: array<string, string>}>>},
     *     daily_snapshot_status: array<string, mixed>,
     *     weekly_import_status: array{week_start: string, week_end: string, sources: array{jre_point: array{state: 'updated'|'stale'|'missing', last_updated_at: string|null, latest_history_date: string|null}, mobile_suica: array{state: 'updated'|'stale'|'missing', last_updated_at: string|null, latest_history_date: string|null}}}
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
                'monthly_report' => null,
                'account_summaries' => [],
                'category_expenses' => [],
                'monthly_trends' => [],
                'yearly_summaries' => $this->yearlySummaryService->handle($user, $period['year_start']),
                'yearly_category_expenses' => $this->yearlyCategoryExpenseSummaryService->handle($user, $period['year_start']),
                'yearly_trends' => $this->yearlyMonthlyTrendService->handle($user, $period['year_start']),
                'net_worth_trends' => $this->netWorthTrendService->handle(
                    $user,
                    $period['year_start'],
                    $period['year_start']->endOfYear(),
                ),
                'asset_history_trends' => $this->assetHistoryTrendService->handle($user),
                'daily_snapshot_status' => $this->dailySnapshotStatusService->handle($user),
                'weekly_import_status' => $this->weeklyImportStatusService->handle($user),
            ];
        }

        $netWorthTrends = $this->netWorthTrendService->handle(
            $user,
            $period['month_start'],
            $period['month_start']->endOfMonth(),
        );
        $categoryExpenses = $this->categoryExpenseSummaryService->handle(
            $user,
            $period['month_start'],
        );

        return [
            'selected_view' => $period['selected_view'],
            'selected_month' => $period['selected_month'],
            'selected_year' => $period['selected_year'],
            'selected_period_label' => $period['selected_period_label'],
            'year_options' => $period['year_options'],
            'month_options' => $period['month_options'],
            'year_view_ready' => true,
            'monthly_summaries' => $this->monthlySummaryService->handle($user, $period['month_start']),
            'monthly_report' => $this->monthlyReportService->handle(
                $user,
                $period['month_start'],
                $netWorthTrends,
                $categoryExpenses,
            ),
            'account_summaries' => $this->accountBalanceSummaryService->handle($user, $period['month_start']),
            'category_expenses' => $categoryExpenses,
            'monthly_trends' => $this->monthlyTrendService->handle($user, $period['month_start']),
            'yearly_summaries' => [],
            'yearly_category_expenses' => [],
            'yearly_trends' => [],
            'net_worth_trends' => $netWorthTrends,
            'asset_history_trends' => $this->assetHistoryTrendService->handle($user),
            'daily_snapshot_status' => $this->dailySnapshotStatusService->handle($user),
            'weekly_import_status' => $this->weeklyImportStatusService->handle($user),
        ];
    }
}
