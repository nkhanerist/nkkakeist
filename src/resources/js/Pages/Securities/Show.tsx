import AppPage from '@/Components/AppPage';
import LineTrendChart from '@/Components/Charts/LineTrendChart';
import {
    SecuritiesAccountDetail,
    SecuritiesPeriodOption,
    SecuritiesPositionDetail,
    SecuritiesPositionItem,
    SecuritiesSnapshotRow,
} from '@/types/securities';
import { TrendSeries } from '@/types/chart';
import { formatMoney } from '@/utils/currency';
import { Link, router } from '@inertiajs/react';

type ShowProps = {
    selected_period: string;
    period_options: SecuritiesPeriodOption[];
    period_label: string;
    account: SecuritiesAccountDetail;
    account_series: TrendSeries;
    snapshots: SecuritiesSnapshotRow[];
    positions_as_of_date: string | null;
    latest_positions: SecuritiesPositionItem[];
    selected_position_key: string | null;
    selected_position: SecuritiesPositionDetail | null;
};

const signedMoney = (amount: string | null, currency: string) => {
    if (amount === null) {
        return '—';
    }

    return `${Number(amount) > 0 ? '+' : ''}${formatMoney(amount, currency)} ${currency}`;
};

const amountTone = (amount: string | null) => {
    if (amount === null || Number(amount) === 0) {
        return 'text-slate-500';
    }

    return Number(amount) > 0 ? 'text-emerald-700' : 'text-rose-700';
};

const decimalValue = (value: string | null) => {
    if (value === null) {
        return '—';
    }

    return Number(value).toLocaleString('ja-JP', {
        maximumFractionDigits: 8,
    });
};

const priceValue = (value: string | null, currency: string) => {
    if (value === null || Number(value) === 0) {
        return '—';
    }

    return `${formatMoney(value, currency)} ${currency}`;
};

export default function Show({
    selected_period,
    period_options,
    period_label,
    account,
    account_series,
    snapshots,
    positions_as_of_date,
    latest_positions,
    selected_position_key,
    selected_position,
}: ShowProps) {
    const changePeriod = (period: string) => {
        router.get(
            route('securities.show', account.id),
            {
                period,
                ...(selected_position_key
                    ? { position: selected_position_key }
                    : {}),
            },
            { preserveState: true, replace: true },
        );
    };

    return (
        <AppPage
            title={account.name}
            description="証券口座の評価額履歴と銘柄別内訳を確認します。"
        >
            <div className="space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={route('securities.index', {
                            period: selected_period,
                        })}
                        className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                    >
                        ← 証券一覧へ戻る
                    </Link>
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('accounts.snapshots.index', account.id)}
                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:border-indigo-300 hover:text-indigo-700"
                        >
                            評価額管理
                        </Link>
                        <Link
                            href={route('transactions.index', {
                                account_id: account.id,
                                calculation_target: 'all',
                            })}
                            className="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                        >
                            関連取引を見る
                        </Link>
                    </div>
                </div>

                <section className="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">表示期間</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            選択した期間内の評価額を比較します。
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

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            口座評価額
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {period_label}の最初と最後の評価額から期間内増減を計算します。
                        </p>
                    </div>
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">最新評価額</p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">
                                {account.latest_valuation === null
                                    ? '—'
                                    : `${formatMoney(account.latest_valuation, account.currency)} ${account.currency}`}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {account.latest_date ?? '期間内データなし'}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">期間内増減</p>
                            <p className={`mt-2 text-xl font-semibold ${amountTone(account.change_amount)}`}>
                                {signedMoney(account.change_amount, account.currency)}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {account.snapshot_count < 2
                                    ? '比較には2日分以上必要です'
                                    : `${account.snapshot_count}日分を比較`}
                            </p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">残高取得日数</p>
                            <p className="mt-2 text-xl font-semibold text-slate-900">
                                {account.snapshot_count}日分
                            </p>
                            <p className="mt-1 text-xs text-slate-500">選択期間内</p>
                        </div>
                        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p className="text-xs font-medium text-slate-500">最新取得元</p>
                            <p className="mt-2 text-lg font-semibold text-slate-900">
                                {account.latest_source ?? '—'}
                            </p>
                            <p className="mt-1 text-xs text-slate-500">
                                {positions_as_of_date
                                    ? `銘柄内訳 ${positions_as_of_date}`
                                    : '銘柄内訳なし'}
                            </p>
                        </div>
                    </div>
                    <LineTrendChart
                        series={[account_series]}
                        currency={account.currency}
                        emptyMessage="この期間の口座評価額はありません。"
                    />
                </section>

                {selected_position ? (
                    <section
                        id="position-detail"
                        className="space-y-5 rounded-3xl border border-indigo-200 bg-indigo-50/40 p-5 sm:p-6"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-[0.16em] text-indigo-600">
                                    Position detail
                                </p>
                                <h2 className="mt-1 text-xl font-semibold text-slate-900">
                                    {selected_position.instrument_name}
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    {[
                                        selected_position.instrument_code,
                                        selected_position.asset_class,
                                    ]
                                        .filter(Boolean)
                                        .join('・') || '銘柄コード・資産区分なし'}
                                </p>
                            </div>
                            <Link
                                href={route('securities.show', {
                                    account: account.id,
                                    period: selected_period,
                                })}
                                className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            >
                                銘柄選択を解除
                            </Link>
                        </div>

                        <dl className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                            {[
                                ['評価額', `${formatMoney(selected_position.latest.valuation, selected_position.currency)} ${selected_position.currency}`],
                                ['評価損益', signedMoney(selected_position.latest.unrealized_gain, selected_position.currency)],
                                ['取得価額', priceValue(selected_position.latest.acquisition_cost, selected_position.currency)],
                                ['保有数量', decimalValue(selected_position.latest.quantity)],
                                ['現在値', priceValue(selected_position.latest.unit_price, selected_position.currency)],
                                ['平均取得単価', priceValue(selected_position.latest.average_acquisition_price, selected_position.currency)],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-xl border border-white bg-white p-3 shadow-sm"
                                >
                                    <dt className="text-xs text-slate-500">{label}</dt>
                                    <dd className="mt-1 font-semibold text-slate-900">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <LineTrendChart
                            series={[selected_position.series]}
                            currency={selected_position.currency}
                            emptyMessage="この期間の銘柄評価額はありません。"
                            height={240}
                        />

                        <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">取得日</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">評価額</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">前回比</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">数量</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">現在値</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">評価損益</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">取得元</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200">
                                    {selected_position.history.map((row) => (
                                        <tr key={row.date}>
                                            <td className="px-4 py-3 text-slate-700">{row.date}</td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(row.valuation, selected_position.currency)}
                                            </td>
                                            <td className={`px-4 py-3 text-right font-medium ${amountTone(row.change_amount)}`}>
                                                {signedMoney(row.change_amount, selected_position.currency)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {decimalValue(row.quantity)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {priceValue(
                                                    row.unit_price,
                                                    selected_position.currency,
                                                )}
                                            </td>
                                            <td className={`px-4 py-3 text-right font-medium ${amountTone(row.unrealized_gain)}`}>
                                                {signedMoney(row.unrealized_gain, selected_position.currency)}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {row.source_name ?? '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ) : null}

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            最新の銘柄内訳
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {positions_as_of_date
                                ? `${positions_as_of_date} に取得した内訳です。銘柄を選ぶと日次履歴を表示します。`
                                : 'この期間の銘柄内訳はありません。'}
                        </p>
                    </div>
                    {latest_positions.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            銘柄別内訳を含む評価額データを取り込んでください。
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-2xl border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">銘柄</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">資産区分</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">構成比</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">評価額</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">評価損益</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">期間内増減</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {latest_positions.map((position) => (
                                        <tr
                                            key={position.position_key}
                                            className={
                                                selected_position_key === position.position_key
                                                    ? 'bg-indigo-50/70'
                                                    : 'hover:bg-slate-50'
                                            }
                                        >
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`${route('securities.show', {
                                                        account: account.id,
                                                        period: selected_period,
                                                        position: position.position_key,
                                                    })}#position-detail`}
                                                    className="font-medium text-indigo-700 hover:text-indigo-900"
                                                >
                                                    {position.instrument_name}
                                                    <span className="ml-2 text-xs font-normal text-indigo-500">
                                                        履歴を見る →
                                                    </span>
                                                </Link>
                                                <p className="mt-1 text-xs text-slate-500">
                                                    {position.instrument_code ?? 'コードなし'}・{position.history_count}日分
                                                </p>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {position.asset_class ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {position.share_percent === null
                                                    ? '—'
                                                    : `${position.share_percent}%`}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(position.valuation, position.currency)}{' '}
                                                {position.currency}
                                            </td>
                                            <td className={`px-4 py-3 text-right font-medium ${amountTone(position.unrealized_gain)}`}>
                                                {signedMoney(position.unrealized_gain, position.currency)}
                                            </td>
                                            <td className={`px-4 py-3 text-right font-medium ${amountTone(position.change_amount)}`}>
                                                {signedMoney(position.change_amount, position.currency)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="text-lg font-semibold text-slate-900">評価額履歴</h2>
                        <p className="mt-1 text-sm text-slate-500">
                            口座合計の取得履歴です。取込データがある場合は元の取込結果を確認できます。
                        </p>
                    </div>
                    {snapshots.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            この期間の評価額履歴はありません。
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-2xl border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50">
                                    <tr>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">取得日</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">評価額</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">前回比</th>
                                        <th className="px-4 py-3 text-right font-semibold text-slate-600">銘柄数</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">取得元</th>
                                        <th className="px-4 py-3 text-left font-semibold text-slate-600">取込</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {snapshots.map((snapshot) => (
                                        <tr key={snapshot.id}>
                                            <td className="px-4 py-3 text-slate-700">{snapshot.date}</td>
                                            <td className="px-4 py-3 text-right font-medium text-slate-900">
                                                {formatMoney(snapshot.valuation, account.currency)}{' '}
                                                {account.currency}
                                            </td>
                                            <td className={`px-4 py-3 text-right font-medium ${amountTone(snapshot.change_amount)}`}>
                                                {signedMoney(snapshot.change_amount, account.currency)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {snapshot.position_count}件
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {snapshot.source_name ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {snapshot.import_id === null ? (
                                                    <span className="text-slate-400">手動</span>
                                                ) : (
                                                    <Link
                                                        href={route('imports.show', snapshot.import_id)}
                                                        className="font-medium text-indigo-700 hover:text-indigo-900"
                                                    >
                                                        #{snapshot.import_id}を確認
                                                    </Link>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AppPage>
    );
}
