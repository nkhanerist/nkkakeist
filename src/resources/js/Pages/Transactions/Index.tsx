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

type IndexProps = {
    transactions: PaginatedTransactions;
    filters: TransactionFilters;
    typeOptions: TransactionTypeOption[];
    accountOptions: TransactionAccountOption[];
    categoryOptions: TransactionCategoryOption[];
};

export default function Index({
    transactions,
    filters,
    typeOptions,
    accountOptions,
    categoryOptions,
}: IndexProps) {
    const { data, setData, get, processing } = useForm<TransactionFilters>({
        date_from: filters.date_from ?? '',
        date_to: filters.date_to ?? '',
        account_id: filters.account_id ?? '',
        category_id: filters.category_id ?? '',
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
        if (!window.confirm('この取引を削除しますか？')) {
            return;
        }

        router.delete(route('transactions.destroy', transactionId), {
            preserveScroll: true,
        });
    };

    return (
        <AppPage
            title="Transactions"
            description="入出金や振替の取引を一覧管理します。"
        >
            <div className="space-y-6">
                <div className="flex flex-wrap justify-end gap-3">
                    <Link
                        href={route('transactions.category-review.index')}
                        className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-indigo-700 shadow-sm transition hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        カテゴリ未設定を確認
                    </Link>
                    <Link
                        href={route('transactions.create')}
                        className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        取引を追加
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
                            開始日
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
                            終了日
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
                            キーワード
                        </label>
                        <TextInput
                            id="keyword"
                            className="mt-1 block w-full"
                            value={data.keyword}
                            onChange={(event) =>
                                setData('keyword', event.target.value)
                            }
                            placeholder="店舗名・摘要・メモ"
                        />
                    </div>

                    <div>
                        <label
                            htmlFor="account_id"
                            className="text-sm font-medium text-slate-700"
                        >
                            口座
                        </label>
                        <select
                            id="account_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.account_id}
                            onChange={(event) =>
                                setData('account_id', event.target.value)
                            }
                        >
                            <option value="">すべて</option>
                            {accountOptions.map((account) => (
                                <option key={account.id} value={account.id}>
                                    {account.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="category_id"
                            className="text-sm font-medium text-slate-700"
                        >
                            カテゴリ
                        </label>
                        <select
                            id="category_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.category_id}
                            onChange={(event) =>
                                setData('category_id', event.target.value)
                            }
                        >
                            <option value="">すべて</option>
                            {categoryOptions.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="type"
                            className="text-sm font-medium text-slate-700"
                        >
                            種別
                        </label>
                        <select
                            id="type"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) => setData('type', event.target.value)}
                        >
                            <option value="">すべて</option>
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
                            確認状態
                        </label>
                        <select
                            id="is_confirmed"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.is_confirmed}
                            onChange={(event) =>
                                setData('is_confirmed', event.target.value)
                            }
                        >
                            <option value="">すべて</option>
                            <option value="1">確認済み</option>
                            <option value="0">未確認</option>
                        </select>
                    </div>

                    <div>
                        <label
                            htmlFor="calculation_target"
                            className="text-sm font-medium text-slate-700"
                        >
                            集計対象
                        </label>
                        <select
                            id="calculation_target"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.calculation_target}
                            onChange={(event) =>
                                setData(
                                    'calculation_target',
                                    event.target.value as
                                        | 'all'
                                        | 'included'
                                        | 'excluded',
                                )
                            }
                        >
                            <option value="all">すべて</option>
                            <option value="included">集計対象</option>
                            <option value="excluded">集計対象外</option>
                        </select>
                    </div>

                    <div className="md:col-span-3 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={resetFilters}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            リセット
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            絞り込む
                        </button>
                    </div>
                </form>

                <div className="overflow-x-auto rounded-2xl border border-slate-200">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    取引日
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    種別
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    口座
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    金額
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    カテゴリ
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    小分類
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    摘要 / 店舗名
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    確認
                                </th>
                                <th className="px-4 py-3 text-left font-semibold text-slate-600">
                                    集計
                                </th>
                                <th className="px-4 py-3 text-right font-semibold text-slate-600">
                                    操作
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
                                        条件に一致する取引がありません。
                                    </td>
                                </tr>
                            ) : (
                                transactions.data.map((transaction) => (
                                    <tr key={transaction.id}>
                                        <td className="px-4 py-3 text-slate-700">
                                            <Link
                                                href={route('transactions.show', transaction.id)}
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
                                                    → {transaction.transfer_account.name}
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
                                            {transaction.subcategory?.name ?? '-'}
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
                                                    ? '確認済み'
                                                    : '未確認'}
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
                                                    ? '対象'
                                                    : '除外'}
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
                                                    編集
                                                </Link>
                                                <DangerButton
                                                    type="button"
                                                    onClick={() =>
                                                        handleDelete(transaction.id)
                                                    }
                                                >
                                                    削除
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
                            {transactions.total} 件
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
