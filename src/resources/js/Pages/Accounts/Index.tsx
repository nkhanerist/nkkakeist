import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import { AccountListItem } from '@/types/account';
import { PageProps } from '@/types';
import { getAccountBalanceLabel } from '@/utils/accountType';
import { formatMoney } from '@/utils/currency';
import { Link, router, usePage } from '@inertiajs/react';

type IndexProps = {
    accounts: AccountListItem[];
};

export default function Index({ accounts }: IndexProps) {
    const flashError = usePage<PageProps>().props.flash.error;

    const handleDelete = (account: AccountListItem) => {
        if (! window.confirm(`「${account.name}」を削除しますか？`)) {
            return;
        }

        router.delete(route('accounts.destroy', account.id));
    };

    return (
        <AppPage
            title="Accounts"
            description="口座一覧を確認し、作成・編集・削除を行えます。"
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
                            ログインユーザーに紐づく口座のみ表示しています。
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('accounts.reconciliation.index')}
                            className="inline-flex items-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-semibold tracking-widest text-indigo-800 transition duration-150 ease-in-out hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            残高照合
                        </Link>
                        <Link
                            href={route('accounts.create')}
                            className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:bg-gray-700 focus:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        >
                            口座を追加
                        </Link>
                    </div>
                </div>

                {accounts.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            まだ口座がありません。最初の口座を追加してください。
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-[980px] divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">口座名</th>
                                        <th className="px-4 py-3">種別</th>
                                        <th className="px-4 py-3">資産区分</th>
                                        <th className="px-4 py-3">通貨</th>
                                        <th className="px-4 py-3">初期残高</th>
                                        <th className="px-4 py-3">状態</th>
                                        <th className="px-4 py-3">操作</th>
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
                                                            ? '純資産に含む'
                                                            : '純資産から除外'}
                                                    </p>
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
                                                            ? `${account.opening_balance_date} 取引開始前`
                                                            : `${getAccountBalanceLabel(account.type)}の起点`}
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
                                                        ? '有効'
                                                        : '無効'}
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
                                                            評価額
                                                        </Link>
                                                    ) : null}
                                                    <Link
                                                        href={route(
                                                            'accounts.edit',
                                                            account.id,
                                                        )}
                                                        className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-gray-700 shadow-sm transition duration-150 ease-in-out hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                    >
                                                        編集
                                                    </Link>
                                                    <DangerButton
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(account)
                                                        }
                                                    >
                                                        削除
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
