import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { TransactionDetail } from '@/types/transaction';
import { Link, router } from '@inertiajs/react';
import {
    getAccountBalanceLabel,
    getAccountTypeDescription,
    getAccountTypeLabel,
} from '@/utils/accountType';
import { formatMoney } from '@/utils/currency';

type ShowProps = {
    transaction: TransactionDetail;
};

export default function Show({ transaction }: ShowProps) {
    const handleDelete = () => {
        if (!window.confirm('この取引を削除しますか？')) {
            return;
        }

        router.delete(route('transactions.destroy', transaction.id));
    };

    return (
        <AppPage
            title="取引詳細"
            description="登録済みの取引内容を確認します。"
        >
            <div className="space-y-6">
                <dl className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            取引日
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.transaction_date}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            種別
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.type_label}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            口座
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.account?.name ?? '-'}
                        </dd>
                        {transaction.account ? (
                            <dd className="mt-2 text-xs leading-5 text-slate-500">
                                {getAccountTypeLabel(transaction.account.type)}
                                {' / '}
                                {getAccountBalanceLabel(
                                    transaction.account.type,
                                )}
                                {getAccountTypeDescription(
                                    transaction.account.type,
                                )
                                    ? `。${getAccountTypeDescription(
                                          transaction.account.type,
                                      )}`
                                    : ''}
                            </dd>
                        ) : null}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            振替先口座
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.transfer_account?.name ?? '-'}
                        </dd>
                        {transaction.transfer_account ? (
                            <dd className="mt-2 text-xs leading-5 text-slate-500">
                                {getAccountTypeLabel(
                                    transaction.transfer_account.type,
                                )}
                                {' / '}
                                {getAccountBalanceLabel(
                                    transaction.transfer_account.type,
                                )}
                                {getAccountTypeDescription(
                                    transaction.transfer_account.type,
                                )
                                    ? `。${getAccountTypeDescription(
                                          transaction.transfer_account.type,
                                      )}`
                                    : ''}
                            </dd>
                        ) : null}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            金額
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {formatMoney(transaction.amount, transaction.currency)}{' '}
                            {transaction.currency}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            確認済み
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.is_confirmed ? '確認済み' : '未確認'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            集計対象
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.is_calculation_target ? '対象' : '除外'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            口座残高
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.affects_account_balance ? '反映' : '除外'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            カテゴリ
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.category?.name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            小分類
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.subcategory?.name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            摘要 / 店舗名
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.merchant_name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            支払方法
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.payment_method_label ?? '-'}
                        </dd>
                    </div>
                </dl>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 className="text-sm font-semibold text-slate-900">摘要</h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                        {transaction.description ?? '-'}
                    </p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 className="text-sm font-semibold text-slate-900">メモ</h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                        {transaction.memo ?? '-'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Link
                        href={route('transactions.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        一覧へ戻る
                    </Link>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        編集
                    </Link>
                    <DangerButton type="button" onClick={handleDelete}>
                        削除
                    </DangerButton>
                </div>
            </div>
        </AppPage>
    );
}
