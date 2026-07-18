import {
    DashboardWeeklyImportSourceStatus,
    DashboardWeeklyImportStatus,
} from '@/types/dashboard';
import { Link } from '@inertiajs/react';

type WeeklyImportStatusCardProps = {
    status: DashboardWeeklyImportStatus;
};

const sourceConfig = {
    jre_point: {
        label: 'JRE POINT',
        description: 'ログイン・SMS認証後、履歴を書き出して取り込みます。',
        source: 'jre_point',
    },
    mobile_suica: {
        label: 'モバイルSuica',
        description: '利用履歴PDFを取得し、そのまま取り込みます。',
        source: 'mobile_suica',
    },
} as const;

const stateConfig = {
    updated: {
        label: '今週更新済み',
        badge: 'bg-emerald-100 text-emerald-800',
    },
    stale: {
        label: '今週未更新',
        badge: 'bg-amber-100 text-amber-800',
    },
    missing: {
        label: '未取込',
        badge: 'bg-rose-100 text-rose-800',
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

function SourceStatus({
    sourceKey,
    status,
}: {
    sourceKey: keyof typeof sourceConfig;
    status: DashboardWeeklyImportSourceStatus;
}) {
    const source = sourceConfig[sourceKey];
    const state = stateConfig[status.state];

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-slate-900">{source.label}</h3>
                        <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${state.badge}`}
                        >
                            {state.label}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-slate-600">{source.description}</p>
                </div>

                <Link
                    href={route('imports.create', { source: source.source })}
                    className="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    {status.state === 'updated' ? '再取込する' : '更新する'}
                </Link>
            </div>

            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-xs font-medium text-slate-500">最終更新</dt>
                    <dd className="mt-1 font-medium text-slate-800">
                        {formatDateTime(status.last_updated_at)}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs font-medium text-slate-500">最新履歴日</dt>
                    <dd className="mt-1 font-medium text-slate-800">
                        {status.latest_history_date ?? '履歴なし'}
                    </dd>
                </div>
            </dl>
        </article>
    );
}

export default function WeeklyImportStatusCard({ status }: WeeklyImportStatusCardProps) {
    return (
        <section className="rounded-2xl border border-sky-200 bg-sky-50/60 p-5">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">
                    JRE POINT・モバイルSuicaの週次更新
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    対象期間: {status.week_start}〜{status.week_end}。ログインとSMS認証は本人が行い、取得後のファイルを既存の取込機能へ登録します。
                </p>
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-2">
                <SourceStatus sourceKey="jre_point" status={status.sources.jre_point} />
                <SourceStatus sourceKey="mobile_suica" status={status.sources.mobile_suica} />
            </div>
        </section>
    );
}
