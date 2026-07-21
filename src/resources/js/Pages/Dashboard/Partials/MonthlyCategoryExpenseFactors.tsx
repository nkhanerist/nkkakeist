import { DashboardMonthlyReportCategoryExpenseGroup } from '@/types/dashboard';
import { formatMoney } from '@/utils/currency';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type MonthlyCategoryExpenseFactorsProps = {
    groups: DashboardMonthlyReportCategoryExpenseGroup[];
    selectedMonth: string;
};

type CategoryExpenseItem =
    DashboardMonthlyReportCategoryExpenseGroup['items'][number];

const colors = [
    '#4f46e5',
    '#059669',
    '#e11d48',
    '#d97706',
    '#0284c7',
    '#7c3aed',
    '#0f766e',
    '#c2410c',
    '#be185d',
    '#65a30d',
    '#0369a1',
    '#9333ea',
];

const monthRange = (month: string) => {
    const [yearValue, monthValue] = month.split('-').map(Number);
    const lastDay = new Date(yearValue, monthValue, 0).getDate();

    return {
        date_from: `${month}-01`,
        date_to: `${month}-${String(lastDay).padStart(2, '0')}`,
    };
};

const shiftMonth = (month: string, offset: number) => {
    const [yearValue, monthValue] = month.split('-').map(Number);
    const date = new Date(Date.UTC(yearValue, monthValue - 1 + offset, 1));

    return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}`;
};

const categoryTransactionsHref = (
    range: ReturnType<typeof monthRange>,
    currency: string,
    categoryId: number | null,
) =>
    route('transactions.index', {
        ...range,
        type: 'expense',
        currency,
        calculation_target: 'included',
        filter_panel: 'collapsed',
        ...(categoryId === null
            ? { category_state: 'uncategorized' }
            : { category_id: categoryId, category_state: 'categorized' }),
    });

const signedMoney = (amount: string, currency: string) => {
    const value = Number(amount);

    return `${value > 0 ? '+' : ''}${formatMoney(amount, currency)} ${currency}`;
};

function ChangeItems({
    title,
    items,
    currency,
    currentRange,
    previousRange,
    tone,
}: {
    title: string;
    items: CategoryExpenseItem[];
    currency: string;
    currentRange: ReturnType<typeof monthRange>;
    previousRange: ReturnType<typeof monthRange>;
    tone: 'increase' | 'decrease';
}) {
    const { t } = useTranslation('dashboard');
    const toneClasses =
        tone === 'increase'
            ? 'bg-rose-50 text-rose-700'
            : 'bg-emerald-50 text-emerald-700';

    return (
        <div>
            <div className="flex items-center justify-between gap-3">
                <h5 className="text-sm font-semibold text-slate-800">
                    {title}
                </h5>
                <span
                    className={`shrink-0 whitespace-nowrap rounded-full px-2.5 py-1 text-xs font-semibold ${toneClasses}`}
                >
                    {t('monthlyReport.categoryFactors.categoryCount', {
                        count: items.length,
                    })}
                </span>
            </div>
            {items.length === 0 ? (
                <p className="mt-3 rounded-xl bg-slate-50 px-3 py-5 text-center text-sm text-slate-500">
                    {t('monthlyReport.categoryFactors.noChanges')}
                </p>
            ) : (
                <div className="mt-3 space-y-3">
                    {items.map((item) => (
                        <div
                            key={`${tone}-${item.category_id ?? 'uncategorized'}`}
                            className="rounded-xl border border-slate-200 bg-white p-3"
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                    <p className="font-medium text-slate-900">
                                        {item.category_name}
                                    </p>
                                    <p className="mt-1 text-xs text-slate-500">
                                        <span className="whitespace-nowrap">
                                            {t(
                                                'monthlyReport.categoryFactors.previousAmount',
                                                {
                                                    amount: formatMoney(
                                                        item.previous_amount,
                                                        currency,
                                                    ),
                                                },
                                            )}
                                        </span>{' '}
                                        <span className="block whitespace-nowrap sm:inline">
                                            {t(
                                                'monthlyReport.categoryFactors.currentAmount',
                                                {
                                                    amount: formatMoney(
                                                        item.current_amount,
                                                        currency,
                                                    ),
                                                    currency,
                                                },
                                            )}
                                        </span>
                                    </p>
                                </div>
                                <span
                                    className={`shrink-0 text-sm font-semibold ${
                                        tone === 'increase'
                                            ? 'text-rose-700'
                                            : 'text-emerald-700'
                                    }`}
                                >
                                    {signedMoney(item.change_amount, currency)}
                                </span>
                            </div>
                            <div className="mt-2 flex flex-wrap gap-2 text-xs font-medium">
                                {Number(item.current_amount) !== 0 ? (
                                    <Link
                                        href={categoryTransactionsHref(
                                            currentRange,
                                            currency,
                                            item.category_id,
                                        )}
                                        className="rounded-md bg-indigo-50 px-2.5 py-1.5 text-indigo-700 hover:bg-indigo-100"
                                    >
                                        {t(
                                            'monthlyReport.categoryFactors.currentDetails',
                                        )}
                                    </Link>
                                ) : null}
                                {Number(item.previous_amount) !== 0 ? (
                                    <Link
                                        href={categoryTransactionsHref(
                                            previousRange,
                                            currency,
                                            item.category_id,
                                        )}
                                        className="rounded-md bg-slate-100 px-2.5 py-1.5 text-slate-700 hover:bg-slate-200"
                                    >
                                        {t(
                                            'monthlyReport.categoryFactors.previousDetails',
                                        )}
                                    </Link>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function MonthlyCategoryExpenseFactors({
    groups,
    selectedMonth,
}: MonthlyCategoryExpenseFactorsProps) {
    const { t } = useTranslation('dashboard');
    const currentRange = monthRange(selectedMonth);
    const previousRange = monthRange(shiftMonth(selectedMonth, -1));

    return (
        <article className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
            <div>
                <h3 className="font-semibold text-slate-900">
                    {t('monthlyReport.categoryFactors.title')}
                </h3>
                <p className="mt-1 text-xs text-slate-500">
                    {t('monthlyReport.categoryFactors.description')}
                </p>
            </div>

            {groups.length === 0 ? (
                <p className="mt-4 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                    {t('monthlyReport.categoryFactors.empty')}
                </p>
            ) : (
                <div className="mt-5 space-y-6">
                    {groups.map((group) => {
                        const compositionItems = group.items.filter(
                            (item) => Number(item.current_amount) > 0,
                        );
                        const increasedItems = [...group.items]
                            .filter((item) => Number(item.change_amount) > 0)
                            .sort(
                                (left, right) =>
                                    Number(right.change_amount) -
                                    Number(left.change_amount),
                            )
                            .slice(0, 5);
                        const decreasedItems = [...group.items]
                            .filter((item) => Number(item.change_amount) < 0)
                            .sort(
                                (left, right) =>
                                    Number(left.change_amount) -
                                    Number(right.change_amount),
                            )
                            .slice(0, 5);

                        return (
                            <section
                                key={group.currency}
                                className="rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 className="font-semibold text-slate-900">
                                            {group.currency}
                                        </h4>
                                        <p className="mt-1 text-xs text-slate-500">
                                            {t(
                                                'monthlyReport.categoryFactors.comparisonSource',
                                                {
                                                    period: group.previous_month_label,
                                                },
                                            )}
                                        </p>
                                    </div>
                                    <div className="grid grid-cols-3 gap-2 text-right text-xs">
                                        <div>
                                            <p className="text-slate-500">
                                                {t(
                                                    'monthlyReport.categoryFactors.previousMonth',
                                                )}
                                            </p>
                                            <p className="mt-1 font-semibold text-slate-800">
                                                {formatMoney(
                                                    group.previous_total,
                                                    group.currency,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-slate-500">
                                                {t(
                                                    'monthlyReport.categoryFactors.currentMonth',
                                                )}
                                            </p>
                                            <p className="mt-1 font-semibold text-slate-800">
                                                {formatMoney(
                                                    group.current_total,
                                                    group.currency,
                                                )}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-slate-500">
                                                {t(
                                                    'monthlyReport.categoryFactors.change',
                                                )}
                                            </p>
                                            <p
                                                className={`mt-1 font-semibold ${
                                                    Number(
                                                        group.change_amount,
                                                    ) > 0
                                                        ? 'text-rose-700'
                                                        : Number(
                                                                group.change_amount,
                                                            ) < 0
                                                          ? 'text-emerald-700'
                                                          : 'text-slate-700'
                                                }`}
                                            >
                                                {Number(group.change_amount) > 0
                                                    ? '+'
                                                    : ''}
                                                {formatMoney(
                                                    group.change_amount,
                                                    group.currency,
                                                )}
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-5 grid gap-6 2xl:grid-cols-[minmax(0,1fr)_minmax(0,1.25fr)]">
                                    <div>
                                        <h5 className="text-sm font-semibold text-slate-800">
                                            {t(
                                                'monthlyReport.categoryFactors.compositionTitle',
                                            )}
                                        </h5>
                                        {compositionItems.length === 0 ? (
                                            <p className="mt-3 rounded-xl bg-white px-3 py-8 text-center text-sm text-slate-500">
                                                {t(
                                                    'monthlyReport.categoryFactors.compositionEmpty',
                                                )}
                                            </p>
                                        ) : (
                                            <>
                                                <div
                                                    className="mt-3 flex h-4 overflow-hidden rounded-full bg-slate-200"
                                                    role="img"
                                                    aria-label={t(
                                                        'monthlyReport.categoryFactors.compositionAria',
                                                        {
                                                            currency:
                                                                group.currency,
                                                        },
                                                    )}
                                                >
                                                    {compositionItems.map(
                                                        (item, index) => (
                                                            <span
                                                                key={`bar-${item.category_id ?? 'uncategorized'}`}
                                                                style={{
                                                                    width: `${Math.max(0, Number(item.current_share_percent ?? 0))}%`,
                                                                    backgroundColor:
                                                                        colors[
                                                                            index %
                                                                                colors.length
                                                                        ],
                                                                }}
                                                                title={`${item.category_name}: ${item.current_share_percent ?? '0.0'}%`}
                                                            />
                                                        ),
                                                    )}
                                                </div>
                                                <div className="mt-3 grid gap-2 sm:grid-cols-2">
                                                    {compositionItems.map(
                                                        (item, index) => (
                                                            <Link
                                                                key={
                                                                    item.category_id ??
                                                                    'uncategorized'
                                                                }
                                                                href={categoryTransactionsHref(
                                                                    currentRange,
                                                                    group.currency,
                                                                    item.category_id,
                                                                )}
                                                                className="flex items-center justify-between gap-3 rounded-lg px-2 py-2 transition hover:bg-white"
                                                            >
                                                                <span className="flex min-w-0 items-center gap-2">
                                                                    <span
                                                                        className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                                        style={{
                                                                            backgroundColor:
                                                                                colors[
                                                                                    index %
                                                                                        colors.length
                                                                                ],
                                                                        }}
                                                                    />
                                                                    <span className="truncate text-sm font-medium text-slate-700">
                                                                        {
                                                                            item.category_name
                                                                        }
                                                                    </span>
                                                                </span>
                                                                <span className="shrink-0 text-right text-xs text-slate-500">
                                                                    {
                                                                        item.current_share_percent
                                                                    }
                                                                    %
                                                                    <span className="ml-1 font-medium text-slate-700">
                                                                        {formatMoney(
                                                                            item.current_amount,
                                                                            group.currency,
                                                                        )}
                                                                    </span>
                                                                </span>
                                                            </Link>
                                                        ),
                                                    )}
                                                </div>
                                            </>
                                        )}
                                    </div>

                                    <div className="grid gap-5 lg:grid-cols-2">
                                        <ChangeItems
                                            title={t(
                                                'monthlyReport.categoryFactors.increaseTitle',
                                            )}
                                            items={increasedItems}
                                            currency={group.currency}
                                            currentRange={currentRange}
                                            previousRange={previousRange}
                                            tone="increase"
                                        />
                                        <ChangeItems
                                            title={t(
                                                'monthlyReport.categoryFactors.decreaseTitle',
                                            )}
                                            items={decreasedItems}
                                            currency={group.currency}
                                            currentRange={currentRange}
                                            previousRange={previousRange}
                                            tone="decrease"
                                        />
                                    </div>
                                </div>
                            </section>
                        );
                    })}
                </div>
            )}
        </article>
    );
}
