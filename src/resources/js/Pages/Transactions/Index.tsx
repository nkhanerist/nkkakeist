import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import TextInput from '@/Components/TextInput';
import {
    PaginatedTransactions,
    TransactionAccountOption,
    TransactionCategoryOption,
    TransactionFilters,
    TransactionTypeOption,
} from '@/types/transaction';
import { Link, useForm, router } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { formatMoney } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

type TransactionSortKey = TransactionFilters['sort'];
type TransactionSortDirection = TransactionFilters['direction'];

type SortableHeaderProps = {
    label: string;
    sortKey: TransactionSortKey;
    currentSort: TransactionSortKey;
    currentDirection: TransactionSortDirection;
    onSort: (sort: TransactionSortKey) => void;
    title?: string;
};

function SortableHeader({
    label,
    sortKey,
    currentSort,
    currentDirection,
    onSort,
    title,
}: SortableHeaderProps) {
    const active = currentSort === sortKey;

    return (
        <th
            className="px-4 py-3 text-left font-semibold text-slate-600"
            aria-sort={
                active
                    ? currentDirection === 'asc'
                        ? 'ascending'
                        : 'descending'
                    : 'none'
            }
        >
            <button
                type="button"
                onClick={() => onSort(sortKey)}
                className="inline-flex items-center gap-1.5 whitespace-nowrap rounded-sm text-left transition hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                title={title}
            >
                <span>{label}</span>
                <span
                    className={active ? 'text-indigo-600' : 'text-slate-300'}
                    aria-hidden="true"
                >
                    {active ? (currentDirection === 'asc' ? '↑' : '↓') : '↕'}
                </span>
            </button>
        </th>
    );
}

type IndexProps = {
    transactions: PaginatedTransactions;
    filters: TransactionFilters;
    typeOptions: TransactionTypeOption[];
    accountOptions: TransactionAccountOption[];
    categoryOptions: TransactionCategoryOption[];
    currencyOptions: string[];
};

export default function Index({
    transactions,
    filters,
    typeOptions,
    accountOptions,
    categoryOptions,
    currencyOptions,
}: IndexProps) {
    const { t } = useTranslation('transactions');
    const [isFilterPanelOpen, setIsFilterPanelOpen] = useState(
        filters.filter_panel !== 'collapsed',
    );
    const { data, setData, get, processing } = useForm<TransactionFilters>({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        account_id: filters.account_id ?? '',
        category_id: filters.category_id ?? '',
        category_state: filters.category_state ?? 'all',
        currency: filters.currency ?? '',
        type: filters.type ?? '',
        keyword: filters.keyword ?? '',
        is_confirmed: filters.is_confirmed ?? '',
        calculation_target: filters.calculation_target ?? 'all',
        sort: filters.sort ?? 'date',
        direction: filters.direction ?? 'desc',
        filter_panel: filters.filter_panel ?? 'expanded',
    });

    useEffect(() => {
        setIsFilterPanelOpen(filters.filter_panel !== 'collapsed');
        setData({
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
            account_id: filters.account_id ?? '',
            category_id: filters.category_id ?? '',
            category_state: filters.category_state ?? 'all',
            currency: filters.currency ?? '',
            type: filters.type ?? '',
            keyword: filters.keyword ?? '',
            is_confirmed: filters.is_confirmed ?? '',
            calculation_target: filters.calculation_target ?? 'all',
            sort: filters.sort ?? 'date',
            direction: filters.direction ?? 'desc',
            filter_panel: filters.filter_panel ?? 'expanded',
        });
    }, [filters]);

    const activeFilters = useMemo(() => {
        const items: Array<{
            key: string;
            label: string;
            clear: Partial<TransactionFilters>;
        }> = [];

        if (filters.date_from !== '' || filters.date_to !== '') {
            const label =
                filters.date_from !== '' && filters.date_to !== ''
                    ? t('activeFilters.dateRange', {
                          from: filters.date_from,
                          to: filters.date_to,
                      })
                    : filters.date_from !== ''
                      ? t('activeFilters.dateFrom', {
                            date: filters.date_from,
                        })
                      : t('activeFilters.dateTo', { date: filters.date_to });

            items.push({
                key: 'date',
                label,
                clear: { date_from: '', date_to: '' },
            });
        }

        if (filters.account_id !== '') {
            const account = accountOptions.find(
                (option) => String(option.id) === filters.account_id,
            );
            items.push({
                key: 'account',
                label: t('activeFilters.account', {
                    value: account?.name ?? filters.account_id,
                }),
                clear: { account_id: '' },
            });
        }

        if (filters.category_id !== '') {
            const category = categoryOptions.find(
                (option) => String(option.id) === filters.category_id,
            );
            items.push({
                key: 'category',
                label: t('activeFilters.category', {
                    value: category?.name ?? filters.category_id,
                }),
                clear: { category_id: '', category_state: 'all' },
            });
        } else if (filters.category_state !== 'all') {
            items.push({
                key: 'category_state',
                label:
                    filters.category_state === 'categorized'
                        ? t('options.categorized')
                        : t('options.uncategorized'),
                clear: { category_state: 'all' },
            });
        }

        if (filters.currency !== '') {
            items.push({
                key: 'currency',
                label: t('activeFilters.currency', {
                    value: filters.currency,
                }),
                clear: { currency: '' },
            });
        }

        if (filters.type !== '') {
            const type = typeOptions.find(
                (option) => option.value === filters.type,
            );
            items.push({
                key: 'type',
                label: t('activeFilters.type', {
                    value: type?.label ?? filters.type,
                }),
                clear: { type: '' },
            });
        }

        if (filters.keyword !== '') {
            items.push({
                key: 'keyword',
                label: t('activeFilters.keyword', {
                    value: filters.keyword,
                }),
                clear: { keyword: '' },
            });
        }

        if (filters.is_confirmed !== '') {
            items.push({
                key: 'confirmation',
                label:
                    filters.is_confirmed === '1'
                        ? t('options.confirmed')
                        : t('options.unconfirmed'),
                clear: { is_confirmed: '' },
            });
        }

        if (filters.calculation_target !== 'all') {
            items.push({
                key: 'calculation_target',
                label:
                    filters.calculation_target === 'included'
                        ? t('options.included')
                        : t('options.excluded'),
                clear: { calculation_target: 'all' },
            });
        }

        return items;
    }, [accountOptions, categoryOptions, filters, t, typeOptions]);

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        get(route('transactions.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const navigateWithAppliedFilters = (
        overrides: Partial<TransactionFilters>,
    ) => {
        router.get(
            route('transactions.index'),
            {
                ...filters,
                filter_panel: isFilterPanelOpen ? 'expanded' : 'collapsed',
                ...overrides,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    const toggleFilterPanel = () => {
        const nextOpen = !isFilterPanelOpen;

        setIsFilterPanelOpen(nextOpen);
        setData('filter_panel', nextOpen ? 'expanded' : 'collapsed');
        navigateWithAppliedFilters({
            filter_panel: nextOpen ? 'expanded' : 'collapsed',
        });
    };

    const removeFilter = (clear: Partial<TransactionFilters>) => {
        navigateWithAppliedFilters(clear);
    };

    const changeSort = (sort: TransactionSortKey) => {
        const direction: TransactionSortDirection =
            filters.sort === sort
                ? filters.direction === 'asc'
                    ? 'desc'
                    : 'asc'
                : sort === 'date' || sort === 'amount'
                  ? 'desc'
                  : 'asc';

        navigateWithAppliedFilters({ sort, direction });
    };

    const selectSort = (value: string) => {
        const [sort, direction] = value.split(':') as [
            TransactionSortKey,
            TransactionSortDirection,
        ];

        navigateWithAppliedFilters({ sort, direction });
    };

    const resetFilters = () => {
        setIsFilterPanelOpen(true);
        router.get(
            route('transactions.index'),
            {
                date_from: '',
                date_to: '',
                account_id: '',
                category_id: '',
                category_state: 'all',
                currency: '',
                type: '',
                keyword: '',
                is_confirmed: '',
                calculation_target: 'all',
                sort: filters.sort,
                direction: filters.direction,
                filter_panel: 'expanded',
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    };

    const handleDelete = (transactionId: number) => {
        if (!window.confirm(t('index.confirmDelete'))) {
            return;
        }

        router.delete(route('transactions.destroy', transactionId), {
            preserveScroll: true,
        });
    };

    return (
        <AppPage title={t('index.title')} description={t('index.description')}>
            <div className="space-y-6">
                <div className="flex flex-wrap justify-end gap-3">
                    <Link
                        href={route('transactions.category-review.index')}
                        className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.reviewUncategorized')}
                    </Link>
                    <Link
                        href={route('transactions.create')}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.add')}
                    </Link>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="font-semibold text-slate-900">
                                {t('activeFilters.title')}
                            </p>
                            <p className="mt-1 text-sm text-slate-500">
                                {t('activeFilters.resultCount', {
                                    count: transactions.total,
                                })}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {activeFilters.length > 0 && (
                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="rounded-full px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                >
                                    {t('actions.clearAll')}
                                </button>
                            )}
                            <button
                                type="button"
                                onClick={toggleFilterPanel}
                                aria-expanded={isFilterPanelOpen}
                                aria-controls="transaction-filter-panel"
                                className="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            >
                                {isFilterPanelOpen
                                    ? t('actions.hideFilters')
                                    : t('actions.showFilters')}
                                <span aria-hidden="true">
                                    {isFilterPanelOpen ? '↑' : '↓'}
                                </span>
                            </button>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                        {activeFilters.length === 0 ? (
                            <span className="inline-flex rounded-full bg-slate-100 px-3 py-1.5 text-sm text-slate-600">
                                {t('activeFilters.allTransactions')}
                            </span>
                        ) : (
                            activeFilters.map((filter) => (
                                <span
                                    key={filter.key}
                                    className="inline-flex items-center gap-1 rounded-full border border-indigo-200 bg-indigo-50 py-1 pl-3 pr-1.5 text-sm font-medium text-indigo-800"
                                >
                                    {filter.label}
                                    <button
                                        type="button"
                                        onClick={() =>
                                            removeFilter(filter.clear)
                                        }
                                        aria-label={t(
                                            'activeFilters.removeLabel',
                                            { label: filter.label },
                                        )}
                                        className="inline-flex h-6 w-6 items-center justify-center rounded-full text-indigo-500 transition hover:bg-indigo-100 hover:text-indigo-800 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                    >
                                        <span aria-hidden="true">×</span>
                                    </button>
                                </span>
                            ))
                        )}
                    </div>
                </section>

                {isFilterPanelOpen && (
                    <form
                        id="transaction-filter-panel"
                        onSubmit={submitFilters}
                        className="grid gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 md:grid-cols-3"
                    >
                    <div>
                        <label
                            htmlFor="date_from"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.dateFrom')}
                        </label>
                        <TextInput
                            id="date_from"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.date_from}
                            onChange={(event) =>
                                setData('date_from', event.target.value)
                            }
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="date_to"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.dateTo')}
                        </label>
                        <TextInput
                            id="date_to"
                            type="date"
                            className="mt-1 block w-full"
                            value={data.date_to}
                            onChange={(event) =>
                                setData('date_to', event.target.value)
                            }
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="keyword"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.keyword')}
                        </label>
                        <TextInput
                            id="keyword"
                            className="mt-1 block w-full"
                            value={data.keyword}
                            onChange={(event) =>
                                setData('keyword', event.target.value)
                            }
                            placeholder={t('filters.keywordPlaceholder')}
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="account_id"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.relatedAccount')}
                        </label>
                        <select
                            id="account_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.account_id}
                            onChange={(event) =>
                                setData('account_id', event.target.value)
                            }
                        >
                            <option value="">{t('options.all')}</option>
                            {accountOptions.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('filters.relatedAccountHint')}
                        </p>
                    </div>

                    <div>
                        <label
                            htmlFor="category_id"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.category')}
                        </label>
                        <select
                            id="category_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.category_id}
                            onChange={(event) => {
                                const categoryId = event.target.value;

                                setData({
                                    ...data,
                                    category_id: categoryId,
                                    category_state:
                                        categoryId === ''
                                            ? data.category_state
                                            : 'categorized',
                                });
                            }}
                        >
                            <option value="">{t('options.all')}</option>
                            {categoryOptions.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="category_state"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.categoryState')}
                        </label>
                        <select
                            id="category_state"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.category_state}
                            onChange={(event) => {
                                const categoryState = event.target.value as
                                    'all' | 'categorized' | 'uncategorized';

                                setData({
                                    ...data,
                                    category_id:
                                        categoryState === 'uncategorized'
                                            ? ''
                                            : data.category_id,
                                    category_state: categoryState,
                                });
                            }}
                        >
                            <option value="all">{t('options.all')}</option>
                            <option value="categorized">
                                {t('options.categorized')}
                            </option>
                            <option value="uncategorized">
                                {t('options.uncategorized')}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="currency"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.currency')}
                        </label>
                        <select
                            id="currency"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.currency}
                            onChange={(event) =>
                                setData('currency', event.target.value)
                            }
                        >
                            <option value="">{t('options.all')}</option>
                            {currencyOptions.map((currency) => (
                                <option key={currency} value={currency}>
                                    {currency}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="type"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.type')}
                        </label>
                        <select
                            id="type"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
                        >
                            <option value="">{t('options.all')}</option>
                            {typeOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="is_confirmed"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.confirmation')}
                        </label>
                        <select
                            id="is_confirmed"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.is_confirmed}
                            onChange={(event) =>
                                setData('is_confirmed', event.target.value)
                            }
                        >
                            <option value="">{t('options.all')}</option>
                            <option value="1">{t('options.confirmed')}</option>
                            <option value="0">
                                {t('options.unconfirmed')}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="calculation_target"
                            className="text-sm font-medium text-slate-700"
                        >
                            {t('filters.calculationTarget')}
                        </label>
                        <select
                            id="calculation_target"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.calculation_target}
                            onChange={(event) =>
                                setData(
                                    'calculation_target',
                                    event.target.value as
                                        'all' | 'included' | 'excluded',
                                )
                            }
                        >
                            <option value="all">{t('options.all')}</option>
                            <option value="included">
                                {t('options.included')}
                            </option>
                            <option value="excluded">
                                {t('options.excluded')}
                            </option>
                        </select>
                    </div>

                    <div className="md:col-span-3 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {t('actions.reset')}
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {t('actions.filter')}
                        </button>
                    </div>
                    </form>
                )}

                <div className="flex items-center justify-end md:hidden">
                    <label
                        htmlFor="transaction-sort"
                        className="mr-2 text-sm font-medium text-slate-600"
                    >
                        {t('sorting.label')}
                    </label>
                    <select
                        id="transaction-sort"
                        value={`${filters.sort}:${filters.direction}`}
                        onChange={(event) => selectSort(event.target.value)}
                        className="rounded-md border-slate-300 bg-white text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                        <option value="date:desc">
                            {t('sorting.options.dateDesc')}
                        </option>
                        <option value="date:asc">
                            {t('sorting.options.dateAsc')}
                        </option>
                        <option value="amount:desc">
                            {t('sorting.options.amountDesc')}
                        </option>
                        <option value="amount:asc">
                            {t('sorting.options.amountAsc')}
                        </option>
                        <option value="account:asc">
                            {t('sorting.options.accountAsc')}
                        </option>
                        <option value="account:desc">
                            {t('sorting.options.accountDesc')}
                        </option>
                        <option value="category:asc">
                            {t('sorting.options.categoryAsc')}
                        </option>
                        <option value="category:desc">
                            {t('sorting.options.categoryDesc')}
                        </option>
                        <option value="summary:asc">
                            {t('sorting.options.summaryAsc')}
                        </option>
                        <option value="summary:desc">
                            {t('sorting.options.summaryDesc')}
                        </option>
                    </select>
                </div>

                <div className="overflow-x-auto rounded-2xl border border-slate-200">
                    <table className="min-w-[1280px] divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <SortableHeader
                                    label={t('table.date')}
                                    sortKey="date"
                                    currentSort={filters.sort}
                                    currentDirection={filters.direction}
                                    onSort={changeSort}
                                />
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.type')}
                                </th>
                                <SortableHeader
                                    label={t('table.account')}
                                    sortKey="account"
                                    currentSort={filters.sort}
                                    currentDirection={filters.direction}
                                    onSort={changeSort}
                                />
                                <SortableHeader
                                    label={t('table.amount')}
                                    sortKey="amount"
                                    currentSort={filters.sort}
                                    currentDirection={filters.direction}
                                    onSort={changeSort}
                                    title={t('sorting.amountCurrencyHint')}
                                />
                                <SortableHeader
                                    label={t('table.category')}
                                    sortKey="category"
                                    currentSort={filters.sort}
                                    currentDirection={filters.direction}
                                    onSort={changeSort}
                                />
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.subcategory')}
                                </th>
                                <SortableHeader
                                    label={t('table.summary')}
                                    sortKey="summary"
                                    currentSort={filters.sort}
                                    currentDirection={filters.direction}
                                    onSort={changeSort}
                                />
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.confirmation')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.calculation')}
                                </th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                    {t('table.actions')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 bg-white">
                            {transactions.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={10}
                                        className="px-4 py-8 text-center text-slate-500"
                                    >
                                        {t('index.empty')}
                                    </td>
                                </tr>
                            ) : (
                                transactions.data.map((transaction) => (
                                    <tr key={transaction.id}>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            <Link
                                                href={route(
                                                    'transactions.show',
                                                    transaction.id,
                                                )}
                                                className="font-medium text-indigo-600 hover:text-indigo-500"
                                            >
                                                {transaction.transaction_date}
                                            </Link>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            {transaction.type_label}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            {transaction.account?.name ?? '-'}
                                            {transaction.transfer_account && (
                                                <span className="block text-xs text-slate-500">
                                                    →{' '}
                                                    {
                                                        transaction
                                                            .transfer_account
                                                            .name
                                                    }
                                                </span>
                                            )}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            {formatMoney(
                                                transaction.amount,
                                                transaction.currency,
                                            )}{' '}
                                            {transaction.currency}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {transaction.category?.name ?? '-'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {transaction.subcategory?.name ??
                                                '-'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {transaction.merchant_name ??
                                                transaction.description ??
                                                transaction.memo ??
                                                '-'}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                    transaction.is_confirmed
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-amber-100 text-amber-700'
                                                }`}
                                            >
                                                {transaction.is_confirmed
                                                    ? t('status.confirmed')
                                                    : t('status.unconfirmed')}
                                            </span>
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 text-slate-700">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${
                                                    transaction.is_calculation_target
                                                        ? 'bg-sky-100 text-sky-700'
                                                        : 'bg-slate-200 text-slate-700'
                                                }`}
                                            >
                                                {transaction.is_calculation_target
                                                    ? t('status.included')
                                                    : t('status.excluded')}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={route(
                                                        'transactions.edit',
                                                        transaction.id,
                                                    )}
                                                    className="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                >
                                                    {t('actions.edit')}
                                                </Link>
                                                <DangerButton
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(
                                                            transaction.id,
                                                        )
                                                    }
                                                >
                                                    {t('actions.delete')}
                                                </DangerButton>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {transactions.last_page > 1 && (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-slate-500">
                            {transactions.from ?? 0} - {transactions.to ?? 0} /{' '}
                            {t('index.count', { total: transactions.total })}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {transactions.links.map((link, index) => (
                                <Link
                                    key={`${link.label}-${index}`}
                                    href={link.url ?? '#'}
                                    preserveState
                                    preserveScroll
                                    className={`rounded-md px-3 py-2 text-sm ${
                                        link.active
                                            ? 'bg-indigo-600 text-white'
                                            : 'border border-slate-300 bg-white text-slate-700'
                                    } ${link.url === null ? 'pointer-events-none opacity-50' : ''}`}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppPage>
    );
}
