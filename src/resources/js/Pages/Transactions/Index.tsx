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
import { FormEvent } from 'react';
import { formatMoney } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

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
    });

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        get(route('transactions.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
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

                <form
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

                <div className="overflow-x-auto rounded-2xl border border-slate-200">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.date')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.type')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.account')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.amount')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.category')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.subcategory')}
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    {t('table.summary')}
                                </th>
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
                                        <td className="px-4 py-3 text-slate-700">
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
                                        <td className="px-4 py-3 text-slate-700">
                                            {transaction.type_label}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
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
                                        <td className="px-4 py-3 text-slate-700">
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
                                        <td className="px-4 py-3 text-slate-700">
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
                                        <td className="px-4 py-3 text-slate-700">
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
