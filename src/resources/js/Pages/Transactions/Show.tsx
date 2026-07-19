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
import { useTranslation } from 'react-i18next';

type ShowProps = {
    transaction: TransactionDetail;
};

export default function Show({ transaction }: ShowProps) {
    const { t } = useTranslation('transactions');
    const { t: tAccounts } = useTranslation('accounts');

    const handleDelete = () => {
        if (!window.confirm(t('index.confirmDelete'))) {
            return;
        }

        router.delete(route('transactions.destroy', transaction.id));
    };

    return (
        <AppPage title={t('show.title')} description={t('show.description')}>
            <div className="space-y-6">
                <dl className="grid gap-4 md:grid-cols-2">
                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.date')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.transaction_date}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.type')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.type_label}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.account')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.account?.name ?? '-'}
                        </dd>
                        {transaction.account ? (
                            <dd className="mt-2 text-xs leading-5 text-slate-500">
                                {getAccountTypeLabel(
                                    transaction.account.type,
                                    tAccounts,
                                )}
                                {' / '}
                                {getAccountBalanceLabel(
                                    transaction.account.type,
                                    tAccounts,
                                )}
                                {getAccountTypeDescription(
                                    transaction.account.type,
                                    tAccounts,
                                )
                                    ? ` · ${getAccountTypeDescription(
                                          transaction.account.type,
                                          tAccounts,
                                      )}`
                                    : ''}
                            </dd>
                        ) : null}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.transferAccount')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.transfer_account?.name ?? '-'}
                        </dd>
                        {transaction.transfer_account ? (
                            <dd className="mt-2 text-xs leading-5 text-slate-500">
                                {getAccountTypeLabel(
                                    transaction.transfer_account.type,
                                    tAccounts,
                                )}
                                {' / '}
                                {getAccountBalanceLabel(
                                    transaction.transfer_account.type,
                                    tAccounts,
                                )}
                                {getAccountTypeDescription(
                                    transaction.transfer_account.type,
                                    tAccounts,
                                )
                                    ? ` · ${getAccountTypeDescription(
                                          transaction.transfer_account.type,
                                          tAccounts,
                                      )}`
                                    : ''}
                            </dd>
                        ) : null}
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.amount')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {formatMoney(
                                transaction.amount,
                                transaction.currency,
                            )}{' '}
                            {transaction.currency}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.confirmation')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.is_confirmed
                                ? t('status.confirmed')
                                : t('status.unconfirmed')}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.calculationTarget')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.is_calculation_target
                                ? t('status.included')
                                : t('status.excluded')}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.accountBalance')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.affects_account_balance
                                ? t('status.balanceIncluded')
                                : t('status.balanceExcluded')}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.category')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.category?.name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.subcategory')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.subcategory?.name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.summary')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.merchant_name ?? '-'}
                        </dd>
                    </div>

                    <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <dt className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            {t('fields.paymentMethod')}
                        </dt>
                        <dd className="mt-1 text-sm text-slate-900">
                            {transaction.payment_method_label ?? '-'}
                        </dd>
                    </div>
                </dl>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {t('fields.description')}
                    </h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                        {transaction.description ?? '-'}
                    </p>
                </div>

                <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <h2 className="text-sm font-semibold text-slate-900">
                        {t('fields.memo')}
                    </h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-slate-700">
                        {transaction.memo ?? '-'}
                    </p>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-3">
                    <Link
                        href={route('transactions.index')}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.backToList')}
                    </Link>
                    <Link
                        href={route('transactions.edit', transaction.id)}
                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    >
                        {t('actions.edit')}
                    </Link>
                    <DangerButton type="button" onClick={handleDelete}>
                        {t('actions.delete')}
                    </DangerButton>
                </div>
            </div>
        </AppPage>
    );
}
