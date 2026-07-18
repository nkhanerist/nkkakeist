import AppPage from '@/Components/AppPage';
import LineTrendChart from '@/Components/Charts/LineTrendChart';
import Sparkline from '@/Components/Charts/Sparkline';
import { TrendSeries } from '@/types/chart';
import {
    SecuritiesAccount,
    SecuritiesPeriodOption,
    SecuritiesPositionGroup,
} from '@/types/securities';
import { formatMoney } from '@/utils/currency';
import { Link, router } from '@inertiajs/react';

type IndexProps = {
    selected_period: string;
    period_options: SecuritiesPeriodOption[];
    period_label: string;
    accounts: SecuritiesAccount[];
    account_series: TrendSeries[];
    position_groups: SecuritiesPositionGroup[];
};

function changeAmount(series: TrendSeries): number | null {
    if (series.points.length < 2) {
        return null;
    }

    return Number(series.points[series.points.length - 1].value)
        - Number(series.points[0].value);
}

export default function Index({
    selected_period,
    period_options,
    period_label,
    accounts,
    account_series,
    position_groups,
}: IndexProps) {
    const currencies = Array.from(new Set(account_series.map((series) => series.currency)));
    const accountSeriesWithLinks = account_series.map((series) => ({
        ...series,
        href: route('securities.show', {
            account: series.key,
            period: selected_period,
        }),
    }));
    const changePeriod = (period: string) => {
        router.get(
            route('securities.index'),
            { period },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppPage
            title="証券"
            description="証券口座の合計評価額と、銘柄ごとの日次評価額推移を確認します。"
        >
            <div className="space-y-8">
                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">表示期間</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            取込み済みの評価額スナップショットを表示します。
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {period_options.map((option) => (
                            <button
                                key={option.value}
                                type="button"
                                onClick={() => changePeriod(option.value)}
                                className={`rounded-lg border px-4 py-2 text-sm font-medium transition ${
                                    selected_period === option.value
                                        ? 'border-indigo-600 bg-indigo-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700 hover:border-indigo-300 hover:text-indigo-700'
                                }`}
                            >
                                {option.label}
                            </button>
                        ))}
                    </div>
                </section>

                {accounts.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                        <p className="text-sm text-slate-600">
                            証券口座がありません。口座一覧から証券口座を追加してください。
                        </p>
                        <Link
                            href={route('accounts.create')}
                            className="mt-4 inline-flex text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            口座を追加する
                        </Link>
                    </div>
                ) : (
                    <>
                        <section className="space-y-4">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    口座別の最新評価額
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    選択した{period_label}の範囲で最後に取得した評価額です。
                                </p>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                {accounts.map((account) => (
                                    <div
                                        key={account.id}
                                        className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <Link
                                                    href={route('securities.show', {
                                                        account: account.id,
                                                        period: selected_period,
                                                    })}
                                                    className="font-semibold text-indigo-700 hover:text-indigo-900"
                                                >
                                                    {account.name}
                                                </Link>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {account.latest_date ?? '期間内の評価額なし'}
                                                    {account.latest_source ? `・${account.latest_source}` : ''}
                                                </p>
                                            </div>
                                            <div className="flex flex-col items-end gap-2 text-xs font-medium">
                                                <Link
                                                    href={route('securities.show', {
                                                        account: account.id,
                                                        period: selected_period,
                                                    })}
                                                    className="text-indigo-700 hover:text-indigo-900"
                                                >
                                                    詳細を見る →
                                                </Link>
                                                <Link
                                                    href={route('accounts.snapshots.index', account.id)}
                                                    className="text-slate-500 hover:text-slate-700"
                                                >
                                                    評価額管理
                                                </Link>
                                            </div>
                                        </div>
                                        <p className="mt-5 text-2xl font-semibold tracking-tight text-slate-900">
                                            {account.latest_valuation === null
                                                ? '—'
                                                : `${formatMoney(account.latest_valuation, account.currency)} ${account.currency}`}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </section>

                        <section className="space-y-4">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    口座別評価額推移
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    口座合計として保存した日次評価額を比較します。
                                </p>
                            </div>
                            {currencies.map((currency) => (
                                <div
                                    key={currency}
                                    className="space-y-3 rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                                >
                                    <h3 className="text-sm font-semibold text-slate-700">
                                        {currency}
                                    </h3>
                                    <LineTrendChart
                                        series={accountSeriesWithLinks.filter((series) => series.currency === currency)}
                                        currency={currency}
                                        emptyMessage="この期間の口座評価額はありません。"
                                    />
                                </div>
                            ))}
                        </section>

                        <section className="space-y-4">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    銘柄別評価額推移
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    口座を展開すると、各銘柄の最新評価額・期間内増減・日次推移を確認できます。
                                </p>
                            </div>

                            <div className="space-y-3">
                                {position_groups.map((group) => (
                                    <details
                                        key={group.account_id}
                                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center justify-between gap-4 bg-slate-50 px-5 py-4 transition hover:bg-slate-100">
                                            <div>
                                                <h3 className="font-semibold text-slate-900">
                                                    {group.account_name}
                                                </h3>
                                                <p className="mt-1 text-sm text-slate-500">
                                                    銘柄別内訳 {group.series.length}件
                                                </p>
                                            </div>
                                            <span className="text-sm font-medium text-indigo-700">
                                                展開して確認
                                            </span>
                                        </summary>

                                        {group.series.length === 0 ? (
                                            <p className="px-5 py-8 text-center text-sm text-slate-500">
                                                この期間の銘柄別内訳はありません。
                                            </p>
                                        ) : (
                                            <div className="overflow-x-auto">
                                                <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                    <thead className="bg-white">
                                                        <tr>
                                                            <th className="px-5 py-3 text-left font-semibold text-slate-600">銘柄</th>
                                                            <th className="px-5 py-3 text-right font-semibold text-slate-600">最新評価額</th>
                                                            <th className="px-5 py-3 text-right font-semibold text-slate-600">期間内増減</th>
                                                            <th className="px-5 py-3 text-left font-semibold text-slate-600">推移</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-200">
                                                        {group.series.map((series) => {
                                                            const latest = series.points[series.points.length - 1];
                                                            const change = changeAmount(series);

                                                            return (
                                                                <tr key={series.key}>
                                                                    <td className="px-5 py-4">
                                                                        <Link
                                                                            href={`${route('securities.show', {
                                                                                account: group.account_id,
                                                                                period: selected_period,
                                                                                position: series.key,
                                                                            })}#position-detail`}
                                                                            className="font-medium text-indigo-700 hover:text-indigo-900"
                                                                        >
                                                                            {series.label}
                                                                            <span className="ml-2 text-xs font-normal text-indigo-500">
                                                                                詳細 →
                                                                            </span>
                                                                        </Link>
                                                                        <p className="mt-1 text-xs text-slate-500">
                                                                            {latest?.date ?? '日付なし'}・{series.points.length}日分
                                                                        </p>
                                                                    </td>
                                                                    <td className="px-5 py-4 text-right font-medium text-slate-900">
                                                                        {latest
                                                                            ? `${formatMoney(latest.value, series.currency)} ${series.currency}`
                                                                            : '—'}
                                                                    </td>
                                                                    <td className={`px-5 py-4 text-right font-medium ${
                                                                        change === null
                                                                            ? 'text-slate-400'
                                                                            : change >= 0
                                                                              ? 'text-emerald-700'
                                                                              : 'text-rose-700'
                                                                    }`}>
                                                                        {change === null
                                                                            ? '—'
                                                                            : `${change >= 0 ? '+' : ''}${formatMoney(change, series.currency)} ${series.currency}`}
                                                                    </td>
                                                                    <td className="px-5 py-2">
                                                                        <Sparkline
                                                                            points={series.points}
                                                                            tone={change !== null && change < 0 ? 'rose' : 'emerald'}
                                                                        />
                                                                    </td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </details>
                                ))}
                            </div>
                        </section>
                    </>
                )}
            </div>
        </AppPage>
    );
}
