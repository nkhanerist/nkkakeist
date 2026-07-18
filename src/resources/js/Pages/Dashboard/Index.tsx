import AppPage from '@/Components/AppPage';
import SummaryCard from '@/Components/SummaryCard';
import DashboardPeriodSelector from '@/Pages/Dashboard/Partials/DashboardPeriodSelector';
import AssetHistoryTrendSection from '@/Pages/Dashboard/Partials/AssetHistoryTrendSection';
import DailySnapshotStatusCard from '@/Pages/Dashboard/Partials/DailySnapshotStatusCard';
import NetWorthTrendSection from '@/Pages/Dashboard/Partials/NetWorthTrendSection';
import MonthlyReportSection from '@/Pages/Dashboard/Partials/MonthlyReportSection';
import WeeklyImportStatusCard from '@/Pages/Dashboard/Partials/WeeklyImportStatusCard';
import {
    DashboardAccountSummary,
    DashboardAssetHistoryTrend,
    DashboardCategoryExpense,
    DashboardCurrencySummary,
    DashboardDailySnapshotStatus,
    DashboardMonthlyTrend,
    DashboardMonthlyReport,
    DashboardNetWorthTrend,
    DashboardPeriodOption,
    DashboardYearlyCategoryExpenseGroup,
    DashboardYearlyTrend,
    DashboardWeeklyImportStatus,
} from '@/types/dashboard';
import { Link, router } from '@inertiajs/react';
import {
    getAccountBalanceLabel,
    getAccountTypeDescription,
    getAccountTypeLabel,
} from '@/utils/accountType';
import { formatMoney } from '@/utils/currency';
import { useEffect, useState } from 'react';

type DateRange = {
    date_from: string;
    date_to: string;
};

const getMonthDateRange = (month: string): DateRange => {
    const [yearValue, monthValue] = month.split('-').map(Number);
    const lastDay = new Date(yearValue, monthValue, 0).getDate();

    return {
        date_from: `${month}-01`,
        date_to: `${month}-${String(lastDay).padStart(2, '0')}`,
    };
};

const getYearDateRange = (year: string): DateRange => ({
    date_from: `${year}-01-01`,
    date_to: `${year}-12-31`,
});

const buildTransactionHref = (
    range: DateRange,
    filters: Record<string, string | number>,
) => route('transactions.index', { ...range, ...filters });

type IndexProps = {
    selected_view: 'month' | 'year';
    selected_month: string;
    selected_year: string;
    selected_period_label: string;
    year_options: DashboardPeriodOption[];
    month_options: DashboardPeriodOption[];
    year_view_ready: boolean;
    monthly_summaries: DashboardCurrencySummary[];
    monthly_report: DashboardMonthlyReport | null;
    account_summaries: DashboardAccountSummary[];
    category_expenses: DashboardCategoryExpense[];
    monthly_trends: DashboardMonthlyTrend[];
    yearly_summaries: DashboardCurrencySummary[];
    yearly_category_expenses: DashboardYearlyCategoryExpenseGroup[];
    yearly_trends: DashboardYearlyTrend[];
    net_worth_trends: DashboardNetWorthTrend[];
    asset_history_trends: DashboardAssetHistoryTrend[];
    daily_snapshot_status: DashboardDailySnapshotStatus;
    weekly_import_status: DashboardWeeklyImportStatus;
};

export default function Index({
    selected_view,
    selected_month,
    selected_year,
    selected_period_label,
    year_options,
    month_options,
    year_view_ready,
    monthly_summaries,
    monthly_report,
    account_summaries,
    category_expenses,
    monthly_trends,
    yearly_summaries,
    yearly_category_expenses,
    yearly_trends,
    net_worth_trends,
    asset_history_trends,
    daily_snapshot_status,
    weekly_import_status,
}: IndexProps) {
    const [view, setView] = useState<'month' | 'year'>(selected_view);
    const [year, setYear] = useState(selected_year);
    const [month, setMonth] = useState(selected_month.split('-')[1] ?? '01');
    const [processing, setProcessing] = useState(false);

    useEffect(() => {
        setView(selected_view);
        setYear(selected_year);
        setMonth(selected_month.split('-')[1] ?? '01');
    }, [selected_view, selected_month, selected_year]);

    const navigate = (nextView: 'month' | 'year', nextYear: string, nextMonth: string) => {
        setProcessing(true);

        router.get(
            route('dashboard'),
            nextView === 'year'
                ? { view: 'year', year: nextYear, month: `${nextYear}-${nextMonth}` }
                : { view: 'month', month: `${nextYear}-${nextMonth}` },
            {
                preserveState: true,
                replace: true,
                onFinish: () => setProcessing(false),
            },
        );
    };

    const submit = () => {
        navigate(view, year, month);
    };

    const handleViewChange = (nextView: 'month' | 'year') => {
        if (nextView === view) {
            return;
        }

        navigate(nextView, year, month);
    };

    const categoryExpensesByCurrency = Object.entries(
        category_expenses.reduce<Record<string, DashboardCategoryExpense[]>>(
            (carry, item) => {
                carry[item.currency] ??= [];
                carry[item.currency].push(item);

                return carry;
            },
            {},
        ),
    );

    const yearlyCategoryExpensesByCurrency = yearly_category_expenses;
    const selectedMonthRange = getMonthDateRange(selected_month);
    const selectedYearRange = getYearDateRange(selected_year);

    return (
        <AppPage
            title="ダッシュボード"
            description={
                view === 'year'
                    ? `${selected_period_label} の年間収支を確認します。`
                    : `${selected_period_label} の収支と口座状況を確認します。`
            }
        >
            <div className="space-y-8">
                <DashboardPeriodSelector
                    selectedView={view}
                    selectedYear={year}
                    selectedMonth={month}
                    yearOptions={year_options}
                    monthOptions={month_options}
                    processing={processing}
                    onChangeView={handleViewChange}
                    onChangeYear={setYear}
                    onChangeMonth={setMonth}
                    onSubmit={submit}
                />

                <DailySnapshotStatusCard status={daily_snapshot_status} />

                <WeeklyImportStatusCard status={weekly_import_status} />

                {view === 'month' && monthly_report ? (
                    <MonthlyReportSection
                        report={monthly_report}
                        selectedMonth={selected_month}
                        periodLabel={selected_period_label}
                    />
                ) : null}

                <NetWorthTrendSection
                    groups={net_worth_trends}
                    periodLabel={selected_period_label}
                />

                <AssetHistoryTrendSection groups={asset_history_trends} />

                {view === 'month' ? (
                    <>
                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            月次サマリー
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            通貨ごとに今月の収入・支出・収支差額を表示しています。
                        </p>
                    </div>

                    {monthly_summaries.length === 0 ? (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                            当月の収支データはありません。
                        </div>
                    ) : (
                        <div className="space-y-4">
                            {monthly_summaries.map((summary) => (
                                <section key={summary.currency} className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-sm font-semibold text-slate-700">
                                            {summary.currency}
                                        </h3>
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-3">
                                        <Link
                                            href={buildTransactionHref(selectedMonthRange, {
                                                type: 'income',
                                                currency: summary.currency,
                                                calculation_target: 'included',
                                            })}
                                            aria-label={`${selected_period_label}の収入明細を表示`}
                                            className="block rounded-2xl transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            <SummaryCard
                                                label="今月の収入合計"
                                                value={`${formatMoney(summary.income_total, summary.currency)} ${summary.currency}`}
                                                tone="positive"
                                            />
                                        </Link>
                                        <Link
                                            href={buildTransactionHref(selectedMonthRange, {
                                                type: 'expense',
                                                currency: summary.currency,
                                                calculation_target: 'included',
                                            })}
                                            aria-label={`${selected_period_label}の支出明細を表示`}
                                            className="block rounded-2xl transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            <SummaryCard
                                                label="今月の支出合計"
                                                value={`${formatMoney(summary.expense_total, summary.currency)} ${summary.currency}`}
                                                tone="negative"
                                            />
                                        </Link>
                                        <SummaryCard
                                            label="今月の収支差額"
                                            value={`${formatMoney(summary.balance_total, summary.currency)} ${summary.currency}`}
                                            tone={
                                                Number(summary.balance_total) >= 0
                                                    ? 'default'
                                                    : 'negative'
                                            }
                                        />
                                    </div>
                                </section>
                            ))}
                        </div>
                    )}
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            口座別サマリー
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            初期残高と対象月末までの取引から現在残高相当を表示しています。
                            クレジットカードやコード決済用の口座は、未払残高や請求待ち残高として確認します。
                        </p>
                    </div>

                    <div className="overflow-x-auto rounded-2xl border border-slate-200">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                        口座名
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                        種別
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                        初期残高
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                        現在残高相当
                                    </th>
                                    <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                        状態
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200 bg-white">
                                {account_summaries.map((account) => (
                                    <tr
                                        key={account.id}
                                        className="transition hover:bg-indigo-50/40"
                                    >
                                        <td className="px-4 py-3 text-slate-700">
                                            <Link
                                                href={buildTransactionHref(selectedMonthRange, {
                                                    account_id: account.id,
                                                    currency: account.currency,
                                                    calculation_target: 'all',
                                                })}
                                                className="font-medium text-indigo-700 hover:text-indigo-500 focus:outline-none focus:underline"
                                            >
                                                {account.name}
                                                <span className="mt-1 block text-xs font-normal text-indigo-500">
                                                    選択月の取引を見る →
                                                </span>
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <div className="max-w-xs">
                                                <p className="font-medium text-slate-800">
                                                    {getAccountTypeLabel(
                                                        account.type,
                                                    )}
                                                </p>
                                                {getAccountTypeDescription(
                                                    account.type,
                                                ) ? (
                                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                                        {getAccountTypeDescription(
                                                            account.type,
                                                        )}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {formatMoney(
                                                account.initial_balance,
                                                account.currency,
                                            )}{' '}
                                            {account.currency}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <p className="font-medium text-slate-900">
                                                {formatMoney(
                                                    account.current_balance,
                                                    account.currency,
                                                )}{' '}
                                                {account.currency}
                                            </p>
                                            <p className="mt-1 text-xs text-slate-500">
                                                {getAccountBalanceLabel(
                                                    account.type,
                                                )}
                                            </p>
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                    account.is_active
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-slate-200 text-slate-700'
                                                }`}
                                            >
                                                {account.is_active ? '有効' : '無効'}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-8 lg:grid-cols-2">
                    <section className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                カテゴリ別支出
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                当月の支出をカテゴリ単位で集計しています。
                            </p>
                        </div>

                        <div className="space-y-3">
                            {category_expenses.length === 0 ? (
                                <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                    当月の支出データはありません。
                                </div>
                            ) : (
                                categoryExpensesByCurrency.map(([currency, items]) => {
                                    const categoryMax = Math.max(
                                        ...items.map((item) => Number(item.total_amount)),
                                        0,
                                    );

                                    return (
                                        <div key={currency} className="space-y-3">
                                            <h3 className="text-sm font-semibold text-slate-700">
                                                {currency}
                                            </h3>
                                            {items.map((item, index) => (
                                                <Link
                                                    key={`${currency}-${item.id ?? 'uncategorized'}-${index}`}
                                                    href={buildTransactionHref(selectedMonthRange, {
                                                        type: 'expense',
                                                        currency,
                                                        calculation_target: 'included',
                                                        ...(item.id === null
                                                            ? { category_state: 'uncategorized' }
                                                            : {
                                                                  category_id: item.id,
                                                                  category_state: 'categorized',
                                                              }),
                                                    })}
                                                    aria-label={`${selected_period_label}の${item.name}の支出明細を表示`}
                                                    className="group block rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                >
                                                    <div className="flex items-center justify-between gap-4">
                                                        <p className="font-medium text-slate-900">
                                                            {item.name}
                                                        </p>
                                                        <div className="text-right">
                                                            <p className="text-sm font-semibold text-slate-700">
                                                                {formatMoney(item.total_amount, currency)} {currency}
                                                            </p>
                                                            <p className="mt-1 text-xs font-medium text-indigo-600 group-hover:text-indigo-500">
                                                                明細を見る →
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="mt-3 h-2 rounded-full bg-slate-100">
                                                        <div
                                                            className="h-2 rounded-full bg-rose-400"
                                                            style={{
                                                                width:
                                                                    categoryMax === 0
                                                                        ? '0%'
                                                                        : `${(Number(item.total_amount) / categoryMax) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </Link>
                                            ))}
                                        </div>
                                    );
                                })
                            )}
                        </div>
                    </section>

                    <section className="space-y-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                月別推移
                            </h2>
                            <p className="mt-1 text-sm text-slate-500">
                                直近 6 か月の収入・支出・収支差額を表示しています。
                            </p>
                        </div>

                        <div className="space-y-3">
                            {monthly_trends.map((trend) => (
                                <div
                                    key={trend.month}
                                    className="rounded-2xl border border-slate-200 bg-white p-4"
                                >
                                    <Link
                                        href={route('dashboard', {
                                            view: 'month',
                                            month: trend.month,
                                        })}
                                        className="group flex items-center justify-between gap-4 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                    >
                                        <h3 className="font-medium text-slate-900 group-hover:text-indigo-700">
                                            {trend.label}
                                        </h3>
                                        <span className="text-xs font-semibold text-indigo-600 group-hover:text-indigo-500">
                                            月次レポートを見る →
                                        </span>
                                    </Link>

                                    {trend.summaries.length === 0 ? (
                                        <p className="mt-3 text-sm text-slate-500">
                                            該当月の収支データはありません。
                                        </p>
                                    ) : (
                                        <div className="mt-4 overflow-x-auto">
                                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                <thead className="bg-slate-50">
                                                    <tr>
                                                        <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                            通貨
                                                        </th>
                                                        <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                            収入
                                                        </th>
                                                        <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                            支出
                                                        </th>
                                                        <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                            収支
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-200">
                                                    {trend.summaries.map((summary) => (
                                                        <tr key={`${trend.month}-${summary.currency}`}>
                                                            <td className="px-3 py-2 text-slate-700">
                                                                {summary.currency}
                                                            </td>
                                                            <td className="px-3 py-2 text-slate-700">
                                                                {formatMoney(summary.income_total, summary.currency)}{' '}
                                                                {summary.currency}
                                                            </td>
                                                            <td className="px-3 py-2 text-slate-700">
                                                                {formatMoney(summary.expense_total, summary.currency)}{' '}
                                                                {summary.currency}
                                                            </td>
                                                            <td className="px-3 py-2 font-medium text-slate-900">
                                                                {formatMoney(summary.balance_total, summary.currency)}{' '}
                                                                {summary.currency}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
                    </>
                ) : null}

                {view === 'year' ? (
                    <>
                        {!year_view_ready ? (
                            <section className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                                <h2 className="text-lg font-semibold text-slate-900">
                                    年次表示は準備中です
                                </h2>
                                <p className="mt-2 text-sm text-slate-500">
                                    期間 selector と URL 設計は年次対応済みです。次のフェーズで年次集計を追加します。
                                </p>
                            </section>
                        ) : (
                            <>
                                <section className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-slate-900">
                                            年次サマリー
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            選択年の収入・支出・収支差額を通貨ごとに表示しています。
                                        </p>
                                    </div>

                                    {yearly_summaries.length === 0 ? (
                                        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                            該当年の収支データはありません。
                                        </div>
                                    ) : (
                                        <div className="space-y-4">
                                            {yearly_summaries.map((summary) => (
                                                <section key={`year-${summary.currency}`} className="space-y-3">
                                                    <h3 className="text-sm font-semibold text-slate-700">
                                                        {summary.currency}
                                                    </h3>

                                                    <div className="grid gap-4 md:grid-cols-3">
                                                        <Link
                                                            href={buildTransactionHref(selectedYearRange, {
                                                                type: 'income',
                                                                currency: summary.currency,
                                                                calculation_target: 'included',
                                                            })}
                                                            aria-label={`${selected_period_label}の収入明細を表示`}
                                                            className="block rounded-2xl transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                        >
                                                            <SummaryCard
                                                                label="年間収入合計"
                                                                value={`${formatMoney(summary.income_total, summary.currency)} ${summary.currency}`}
                                                                tone="positive"
                                                            />
                                                        </Link>
                                                        <Link
                                                            href={buildTransactionHref(selectedYearRange, {
                                                                type: 'expense',
                                                                currency: summary.currency,
                                                                calculation_target: 'included',
                                                            })}
                                                            aria-label={`${selected_period_label}の支出明細を表示`}
                                                            className="block rounded-2xl transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                        >
                                                            <SummaryCard
                                                                label="年間支出合計"
                                                                value={`${formatMoney(summary.expense_total, summary.currency)} ${summary.currency}`}
                                                                tone="negative"
                                                            />
                                                        </Link>
                                                        <SummaryCard
                                                            label="年間収支差額"
                                                            value={`${formatMoney(summary.balance_total, summary.currency)} ${summary.currency}`}
                                                            tone={
                                                                Number(summary.balance_total) >= 0
                                                                    ? 'default'
                                                                    : 'negative'
                                                            }
                                                        />
                                                    </div>
                                                </section>
                                            ))}
                                        </div>
                                    )}
                                </section>

                                <section className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-slate-900">
                                            月別推移
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            選択年の 1 月から 12 月までの収入・支出・収支差額を表示しています。
                                        </p>
                                    </div>

                                    <div className="space-y-3">
                                        {yearly_trends.map((trend) => (
                                            <div
                                                key={`year-trend-${trend.month}`}
                                                className="rounded-2xl border border-slate-200 bg-white p-4"
                                            >
                                                <Link
                                                    href={route('dashboard', {
                                                        view: 'month',
                                                        month: trend.month,
                                                    })}
                                                    className="group flex items-center justify-between gap-4 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                >
                                                    <h3 className="font-medium text-slate-900 group-hover:text-indigo-700">
                                                        {trend.label}
                                                    </h3>
                                                    <span className="text-xs font-semibold text-indigo-600 group-hover:text-indigo-500">
                                                        月次レポートを見る →
                                                    </span>
                                                </Link>

                                                {trend.summaries.length === 0 ? (
                                                    <p className="mt-3 text-sm text-slate-500">
                                                        該当月の収支データはありません。
                                                    </p>
                                                ) : (
                                                    <div className="mt-4 overflow-x-auto">
                                                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                            <thead className="bg-slate-50">
                                                                <tr>
                                                                    <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                                        通貨
                                                                    </th>
                                                                    <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                                        収入
                                                                    </th>
                                                                    <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                                        支出
                                                                    </th>
                                                                    <th className="px-3 py-2 text-left font-semibold text-slate-600">
                                                                        収支
                                                                    </th>
                                                                </tr>
                                                            </thead>
                                                            <tbody className="divide-y divide-slate-200">
                                                                {trend.summaries.map((summary) => (
                                                                    <tr key={`${trend.month}-${summary.currency}`}>
                                                                        <td className="px-3 py-2 text-slate-700">
                                                                            {summary.currency}
                                                                        </td>
                                                                        <td className="px-3 py-2 text-slate-700">
                                                                            {formatMoney(summary.income_total, summary.currency)}{' '}
                                                                            {summary.currency}
                                                                        </td>
                                                                        <td className="px-3 py-2 text-slate-700">
                                                                            {formatMoney(summary.expense_total, summary.currency)}{' '}
                                                                            {summary.currency}
                                                                        </td>
                                                                        <td className="px-3 py-2 font-medium text-slate-900">
                                                                            {formatMoney(summary.balance_total, summary.currency)}{' '}
                                                                            {summary.currency}
                                                                        </td>
                                                                    </tr>
                                                                ))}
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </section>

                                <section className="space-y-4">
                                    <div>
                                        <h2 className="text-lg font-semibold text-slate-900">
                                            年次カテゴリ別支出
                                        </h2>
                                        <p className="mt-1 text-sm text-slate-500">
                                            選択年の支出をカテゴリ単位で通貨ごとに集計しています。
                                        </p>
                                    </div>

                                    <div className="space-y-3">
                                        {yearlyCategoryExpensesByCurrency.length === 0 ? (
                                            <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                                該当年のカテゴリ別支出データはありません。
                                            </div>
                                        ) : (
                                            yearlyCategoryExpensesByCurrency.map((group) => {
                                                const categoryMax = Math.max(
                                                    ...group.items.map((item) => Number(item.total_amount)),
                                                    0,
                                                );

                                                return (
                                                    <div key={`yearly-category-${group.currency}`} className="space-y-3">
                                                        <h3 className="text-sm font-semibold text-slate-700">
                                                            {group.currency}
                                                        </h3>
                                                        {group.items.map((item, index) => (
                                                            <Link
                                                                key={`${group.currency}-${item.category_id ?? 'uncategorized'}-${index}`}
                                                                href={buildTransactionHref(selectedYearRange, {
                                                                    type: 'expense',
                                                                    currency: group.currency,
                                                                    calculation_target: 'included',
                                                                    ...(item.category_id === null
                                                                        ? { category_state: 'uncategorized' }
                                                                        : {
                                                                              category_id: item.category_id,
                                                                              category_state: 'categorized',
                                                                          }),
                                                                })}
                                                                aria-label={`${selected_period_label}の${item.category_name}の支出明細を表示`}
                                                                className="group block rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                            >
                                                                <div className="flex items-center justify-between gap-4">
                                                                    <p className="font-medium text-slate-900">
                                                                        {item.category_name}
                                                                    </p>
                                                                    <div className="text-right">
                                                                        <p className="text-sm font-semibold text-slate-700">
                                                                            {formatMoney(item.total_amount, group.currency)} {group.currency}
                                                                        </p>
                                                                        <p className="mt-1 text-xs font-medium text-indigo-600 group-hover:text-indigo-500">
                                                                            明細を見る →
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                                <div className="mt-3 h-2 rounded-full bg-slate-100">
                                                                    <div
                                                                        className="h-2 rounded-full bg-rose-400"
                                                                        style={{
                                                                            width:
                                                                                categoryMax === 0
                                                                                    ? '0%'
                                                                                    : `${(Number(item.total_amount) / categoryMax) * 100}%`,
                                                                        }}
                                                                    />
                                                                </div>
                                                            </Link>
                                                        ))}
                                                    </div>
                                                );
                                            })
                                        )}
                                    </div>
                                </section>
                            </>
                        )}
                    </>
                ) : null}
            </div>
        </AppPage>
    );
}
