import { formatMoney } from '@/utils/currency';
import {
    DashboardMonthlyReport,
    DashboardMonthlyReportComparison,
} from '@/types/dashboard';
import { Link } from '@inertiajs/react';

type MonthlyReportSectionProps = {
    report: DashboardMonthlyReport;
    selectedMonth: string;
    periodLabel: string;
};

const getMonthDateRange = (month: string) => {
    const [yearValue, monthValue] = month.split('-').map(Number);
    const lastDay = new Date(yearValue, monthValue, 0).getDate();

    return {
        date_from: `${month}-01`,
        date_to: `${month}-${String(lastDay).padStart(2, '0')}`,
    };
};

const signedMoney = (amount: string, currency: string) => {
    const value = Number(amount);
    const sign = value > 0 ? '+' : '';

    return `${sign}${formatMoney(amount, currency)} ${currency}`;
};

const changeTone = (amount: string, inverse = false) => {
    const value = Number(amount);

    if (value === 0) {
        return 'text-slate-600';
    }

    const positive = inverse ? value < 0 : value > 0;

    return positive ? 'text-emerald-700' : 'text-rose-700';
};

const ComparisonLine = ({
    label,
    comparison,
    field,
    currency,
    inverse = false,
}: {
    label: string;
    comparison: DashboardMonthlyReportComparison;
    field: 'income' | 'expense';
    currency: string;
    inverse?: boolean;
}) => {
    const amount = comparison[`${field}_change_amount`];
    const percent = comparison[`${field}_change_percent`];

    return (
        <div className="flex items-center justify-between gap-3 text-sm">
            <span className="text-slate-500">{label}</span>
            <span className={`font-medium ${changeTone(amount, inverse)}`}>
                {signedMoney(amount, currency)}
                <span className="ml-1 text-xs font-normal">
                    ({percent === null ? '比較元 0' : `${Number(percent) > 0 ? '+' : ''}${percent}%`})
                </span>
            </span>
        </div>
    );
};

export default function MonthlyReportSection({
    report,
    selectedMonth,
    periodLabel,
}: MonthlyReportSectionProps) {
    const range = getMonthDateRange(selectedMonth);
    const merchantsByCurrency = Object.entries(
        report.top_merchants.reduce<
            Record<string, DashboardMonthlyReport['top_merchants']>
        >((groups, merchant) => {
            groups[merchant.currency] ??= [];
            groups[merchant.currency].push(merchant);

            return groups;
        }, {}),
    );

    return (
        <section className="space-y-5 rounded-3xl border border-indigo-100 bg-gradient-to-br from-white via-white to-indigo-50/70 p-5 shadow-sm sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">
                        Monthly report
                    </p>
                    <h2 className="mt-1 text-xl font-semibold text-slate-900">
                        {periodLabel}のマンスリーレポート
                    </h2>
                    <p className="mt-1 text-sm text-slate-500">
                        前月・前年同月との比較、支出傾向、確認が必要なデータをまとめています。
                    </p>
                </div>
                <Link
                    href={route('transactions.index', {
                        ...range,
                        calculation_target: 'all',
                    })}
                    className="inline-flex rounded-full border border-indigo-200 bg-white px-4 py-2 text-sm font-medium text-indigo-700 transition hover:border-indigo-300 hover:bg-indigo-50"
                >
                    この月の全取引を見る
                </Link>
            </div>

            {report.comparison_groups.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-white/70 px-4 py-8 text-center text-sm text-slate-500">
                    当月・比較対象月の収支データはありません。
                </div>
            ) : (
                <div className="grid gap-4 xl:grid-cols-2">
                    {report.comparison_groups.map((group) => (
                        <article
                            key={group.currency}
                            className="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div className="flex items-center justify-between">
                                <h3 className="font-semibold text-slate-900">
                                    収支比較
                                </h3>
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600">
                                    {group.currency}
                                </span>
                            </div>
                            <div className="mt-4 grid gap-5 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs font-medium text-slate-500">
                                        当月収入
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-emerald-700">
                                        {formatMoney(
                                            group.current.income_total,
                                            group.currency,
                                        )}{' '}
                                        {group.currency}
                                    </p>
                                    <div className="mt-3 space-y-2">
                                        <ComparisonLine
                                            label="前月比"
                                            comparison={group.previous_month}
                                            field="income"
                                            currency={group.currency}
                                        />
                                        <ComparisonLine
                                            label="前年同月比"
                                            comparison={group.previous_year}
                                            field="income"
                                            currency={group.currency}
                                        />
                                    </div>
                                </div>
                                <div className="border-t border-slate-100 pt-4 sm:border-l sm:border-t-0 sm:pl-5 sm:pt-0">
                                    <p className="text-xs font-medium text-slate-500">
                                        当月支出
                                    </p>
                                    <p className="mt-1 text-lg font-semibold text-rose-700">
                                        {formatMoney(
                                            group.current.expense_total,
                                            group.currency,
                                        )}{' '}
                                        {group.currency}
                                    </p>
                                    <div className="mt-3 space-y-2">
                                        <ComparisonLine
                                            label="前月比"
                                            comparison={group.previous_month}
                                            field="expense"
                                            currency={group.currency}
                                            inverse
                                        />
                                        <ComparisonLine
                                            label="前年同月比"
                                            comparison={group.previous_year}
                                            field="expense"
                                            currency={group.currency}
                                            inverse
                                        />
                                    </div>
                                </div>
                            </div>
                        </article>
                    ))}
                </div>
            )}

            <div className="grid gap-4 xl:grid-cols-2">
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 className="font-semibold text-slate-900">利用状況</h3>
                    {report.activity_groups.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">
                            当月の集計対象取引はありません。
                        </p>
                    ) : (
                        <div className="mt-3 space-y-4">
                            {report.activity_groups.map((group) => (
                                <div key={group.currency}>
                                    <p className="text-xs font-semibold text-slate-500">
                                        {group.currency}
                                    </p>
                                    <dl className="mt-2 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                        <div>
                                            <dt className="text-xs text-slate-500">取引件数</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">
                                                {group.transaction_count}件
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">支出件数</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">
                                                {group.expense_count}件
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">平均支出</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">
                                                {group.average_expense === null
                                                    ? '—'
                                                    : formatMoney(
                                                          group.average_expense,
                                                          group.currency,
                                                      )}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">最大支出</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">
                                                {group.largest_expense === null
                                                    ? '—'
                                                    : formatMoney(
                                                          group.largest_expense,
                                                          group.currency,
                                                      )}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            ))}
                        </div>
                    )}
                </article>

                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 className="font-semibold text-slate-900">純資産の増減</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        この月に保存された最初と最後の残高取得日を比較します。
                    </p>
                    {report.net_worth_changes.length === 0 ? (
                        <p className="mt-4 text-sm text-slate-500">
                            比較できる残高データがありません。
                        </p>
                    ) : (
                        <div className="mt-3 space-y-3">
                            {report.net_worth_changes.map((change) => (
                                <div
                                    key={change.currency}
                                    className="rounded-xl bg-slate-50 p-3"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="text-xs font-semibold text-slate-500">
                                            {change.currency}
                                        </span>
                                        {change.change_amount === null ? (
                                            <span className="text-sm text-slate-500">
                                                取得日が1日分のため比較不可
                                            </span>
                                        ) : (
                                            <span
                                                className={`font-semibold ${changeTone(change.change_amount)}`}
                                            >
                                                {signedMoney(
                                                    change.change_amount,
                                                    change.currency,
                                                )}
                                            </span>
                                        )}
                                    </div>
                                    <p className="mt-1 text-xs text-slate-500">
                                        {change.start_date ?? '—'} →{' '}
                                        {change.end_date ?? '—'}
                                    </p>
                                </div>
                            ))}
                        </div>
                    )}
                </article>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 className="font-semibold text-slate-900">支出先 上位5件</h3>
                    {merchantsByCurrency.length === 0 ? (
                        <p className="mt-3 text-sm text-slate-500">
                            当月の支出データはありません。
                        </p>
                    ) : (
                        <div className="mt-3 space-y-4">
                            {merchantsByCurrency.map(([currency, merchants]) => (
                                <div key={currency}>
                                    <p className="text-xs font-semibold text-slate-500">
                                        {currency}
                                    </p>
                                    <ol className="mt-2 divide-y divide-slate-100">
                                        {merchants.map((merchant, index) => {
                                            const content = (
                                                <div className="flex items-center justify-between gap-3 py-2.5">
                                                    <div className="min-w-0">
                                                        <span className="mr-2 text-xs font-semibold text-slate-400">
                                                            {index + 1}
                                                        </span>
                                                        <span className="font-medium text-slate-800">
                                                            {merchant.name}
                                                        </span>
                                                        <span className="ml-2 text-xs text-slate-500">
                                                            {merchant.transaction_count}件
                                                        </span>
                                                    </div>
                                                    <span className="shrink-0 font-semibold text-slate-900">
                                                        {formatMoney(
                                                            merchant.total_amount,
                                                            currency,
                                                        )}{' '}
                                                        {currency}
                                                    </span>
                                                </div>
                                            );

                                            return (
                                                <li key={`${merchant.name}-${index}`}>
                                                    {merchant.keyword === null ? (
                                                        content
                                                    ) : (
                                                        <Link
                                                            href={route(
                                                                'transactions.index',
                                                                {
                                                                    ...range,
                                                                    type: 'expense',
                                                                    currency,
                                                                    calculation_target:
                                                                        'included',
                                                                    keyword:
                                                                        merchant.keyword,
                                                                },
                                                            )}
                                                            className="block rounded-lg transition hover:bg-indigo-50"
                                                        >
                                                            {content}
                                                        </Link>
                                                    )}
                                                </li>
                                            );
                                        })}
                                    </ol>
                                </div>
                            ))}
                        </div>
                    )}
                </article>

                <article className="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 className="font-semibold text-slate-900">データ品質</h3>
                    <p className="mt-1 text-xs text-slate-500">
                        数字を確定する前に確認したい項目です。
                    </p>
                    <div className="mt-3 grid gap-3 sm:grid-cols-3">
                        <Link
                            href={route('transactions.index', {
                                ...range,
                                category_state: 'uncategorized',
                                calculation_target: 'included',
                            })}
                            className="rounded-xl bg-amber-50 p-3 transition hover:bg-amber-100"
                        >
                            <p className="text-xs font-medium text-amber-700">カテゴリなし</p>
                            <p className="mt-1 text-2xl font-semibold text-amber-900">
                                {report.quality.uncategorized_count}
                                <span className="ml-1 text-sm">件</span>
                            </p>
                        </Link>
                        <Link
                            href={route('transactions.index', {
                                ...range,
                                is_confirmed: 0,
                                calculation_target: 'all',
                            })}
                            className="rounded-xl bg-rose-50 p-3 transition hover:bg-rose-100"
                        >
                            <p className="text-xs font-medium text-rose-700">未確認取引</p>
                            <p className="mt-1 text-2xl font-semibold text-rose-900">
                                {report.quality.unconfirmed_count}
                                <span className="ml-1 text-sm">件</span>
                            </p>
                        </Link>
                        <Link
                            href={route('imports.index')}
                            className="rounded-xl bg-indigo-50 p-3 transition hover:bg-indigo-100"
                        >
                            <p className="text-xs font-medium text-indigo-700">確認待ち取込</p>
                            <p className="mt-1 text-2xl font-semibold text-indigo-900">
                                {report.quality.pending_import_count}
                                <span className="ml-1 text-sm">件</span>
                            </p>
                        </Link>
                    </div>
                </article>
            </div>
        </section>
    );
}
