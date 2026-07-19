import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { AccountListItem } from '@/types/account';
import { PageProps } from '@/types';
import { formatMoney } from '@/utils/currency';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type IndexProps = {
    accounts: AccountListItem[];
};

export default function Index({ accounts }: IndexProps) {
    const { t } = useTranslation('accounts');
    const flashError = usePage<PageProps>().props.flash.error;

    const handleDelete = (account: AccountListItem) => {
        if (! window.confirm(t('index.confirmDelete', { name: account.name }))) {
            return;
        }

        router.delete(route('accounts.destroy', account.id));
    };

    return (
        <AppPage
            title={t('index.title')}
            description={t('index.description')}
        >
            <div className="space-y-6">
                {flashError ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {flashError}
                    </div>
                ) : null}

                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-sm text-slate-500">
                            {t('index.scope')}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('accounts.reconciliation.index')}
                            className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold tracking-widest text-indigo-800 transition duration-150 ease-in-out hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {t('actions.reconcile')}
                        </Link>
                        <Link
                            href={route('accounts.create')}
                            className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            {t('actions.add')}
                        </Link>
                    </div>
                </div>

                {accounts.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            {t('index.empty')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-[980px] divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">{t('table.name')}</th>
                                        <th className="px-4 py-3">{t('table.type')}</th>
                                        <th className="px-4 py-3">{t('table.balanceRole')}</th>
                                        <th className="px-4 py-3">{t('table.currency')}</th>
                                        <th className="px-4 py-3">{t('table.initialBalance')}</th>
                                        <th className="px-4 py-3">{t('table.status')}</th>
                                        <th className="px-4 py-3">{t('table.actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    {accounts.map((account) => (
                                        <tr key={account.id}>
                                            <td className="px-4 py-4">
                                                <div>
                                                    <p className="font-medium text-slate-900">
                                                        {account.name}
                                                    </p>
                                                    {account.note ? (
                                                        <p className="mt-1 text-xs text-slate-500">
                                                            {account.note}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <p className="font-medium text-slate-800">
                                                    {account.type_label}
                                                </p>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="space-y-1">
                                                    <span
                                                        className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                            account.balance_role === 'asset'
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : account.balance_role === 'liability'
                                                                  ? 'bg-rose-100 text-rose-700'
                                                                  : 'bg-amber-100 text-amber-700'
                                                        }`}
                                                    >
                                                        {account.balance_role_label}
                                                    </span>
                                                    <p className="text-xs text-slate-500">
                                                        {account.balance_method_label}
                                                    </p>
                                                    <p className="text-xs text-slate-500">
                                                        {account.include_in_net_worth
                                                            ? t('status.includedInNetWorth')
                                                            : t('status.excludedFromNetWorth')}
                                                    </p>
                                                    {account.monthly_close_required ? (
                                                        <p className="text-xs font-medium text-indigo-600">
                                                            {t('status.monthlyCloseRequired')}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {account.currency}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div>
                                                    <p>
                                                        {formatMoney(
                                                            account.initial_balance,
                                                            account.currency,
                                                        )}
                                                    </p>
                                                    <p className="mt-1 text-xs text-slate-500">
                                                        {account.opening_balance_date
                                                            ? t('status.beforeTransactions', { date: account.opening_balance_date })
                                                            : t('status.balanceOrigin', {
                                                                  label: t(`balanceLabels.${account.type}`, {
                                                                      defaultValue: t('balanceLabels.default'),
                                                                  }),
                                                              })}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${
                                                        account.is_active
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : 'bg-slate-200 text-slate-600'
                                                    }`}
                                                >
                                                    {account.is_active
                                                        ? t('status.active')
                                                        : t('status.inactive')}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    {account.balance_method === 'snapshot' ? (
                                                        <Link
                                                            href={route(
                                                                'accounts.snapshots.index',
                                                                account.id,
                                                            )}
                                                            className="inline-flex items-center rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-semibold tracking-widest text-emerald-800 shadow-sm transition duration-150 ease-in-out hover:bg-emerald-100 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                                                        >
                                                            {t('actions.valuation')}
                                                        </Link>
                                                    ) : null}
                                                    <Link
                                                        href={route(
                                                            'accounts.edit',
                                                            account.id,
                                                        )}
                                                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    >
                                                        {t('actions.edit')}
                                                    </Link>
                                                    <DangerButton
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(account)
                                                        }
                                                    >
                                                        {t('actions.delete')}
                                                    </DangerButton>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppPage>
    );
}
