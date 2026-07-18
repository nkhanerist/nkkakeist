import { DashboardDailySnapshotStatus } from '@/types/dashboard';
import { Link } from '@inertiajs/react';

type DailySnapshotStatusCardProps = {
    status: DashboardDailySnapshotStatus;
};

const stateStyle = {
    complete: {
        label: '本日分取得済み',
        badge: 'bg-emerald-100 text-emerald-800',
        border: 'border-emerald-200 bg-emerald-50/60',
    },
    partial: {
        label: '一部のみ取得',
        badge: 'bg-amber-100 text-amber-800',
        border: 'border-amber-200 bg-amber-50/60',
    },
    missing: {
        label: '本日分未取得',
        badge: 'bg-rose-100 text-rose-800',
        border: 'border-rose-200 bg-rose-50/60',
    },
} as const;

const formatDateTime = (value: string | null) => {
    if (value === null) {
        return 'まだありません';
    }

    return new Intl.DateTimeFormat('ja-JP', {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

export default function DailySnapshotStatusCard({
    status,
}: DailySnapshotStatusCardProps) {
    const style = stateStyle[status.state];

    return (
        <section className={`rounded-2xl border p-5 ${style.border}`}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-lg font-semibold text-slate-900">
                            本日のMoney Forward取得状況
                        </h2>
                        <span
                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style.badge}`}
                        >
                            {style.label}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-slate-600">
                        Money Forwardを更新してから、既存のワンクリック取得と
                        「公式残高・評価額」取込を利用します。
                    </p>
                </div>

                <Link
                    href={route('imports.create', { source: 'balance_snapshot' })}
                    className="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700"
                >
                    {status.state === 'complete' ? '再取得する' : '本日分を取得する'}
                </Link>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">対象日</dt>
                    <dd className="mt-1 font-semibold text-slate-900">{status.date}</dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">口座残高</dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {status.account_count}口座
                    </dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">銘柄別評価額</dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {status.position_count}件
                    </dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">総資産サマリー</dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {status.asset_history_recorded ? '記録済み' : '未記録'}
                    </dd>
                </div>
            </dl>

            <p className="mt-3 text-xs text-slate-500">
                最終取込: {formatDateTime(status.last_imported_at)}
            </p>
        </section>
    );
}
