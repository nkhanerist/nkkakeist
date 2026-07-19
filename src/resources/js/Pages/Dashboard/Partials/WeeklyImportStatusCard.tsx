import {
    DashboardWeeklyImportSourceStatus,
    DashboardWeeklyImportStatus,
} from '@/types/dashboard';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type WeeklyImportStatusCardProps = {
    status: DashboardWeeklyImportStatus;
};

const sourceConfig = {
    jre_point: {
        source: 'jre_point',
    },
    mobile_suica: {
        source: 'mobile_suica',
    },
} as const;

const stateConfig = {
    updated: {
        badge: 'bg-emerald-100 text-emerald-800',
    },
    stale: {
        badge: 'bg-amber-100 text-amber-800',
    },
    missing: {
        badge: 'bg-rose-100 text-rose-800',
    },
} as const;

const formatDateTime = (
    value: string | null,
    locale: string,
    emptyLabel: string,
) => {
    if (value === null) {
        return emptyLabel;
    }

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const formatDate = (value: string, locale: string) =>
    new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
    }).format(new Date(`${value}T00:00:00`));

function SourceStatus({
    sourceKey,
    status,
}: {
    sourceKey: keyof typeof sourceConfig;
    status: DashboardWeeklyImportSourceStatus;
}) {
    const { t, i18n } = useTranslation('dashboard');
    const locale = i18n.resolvedLanguage === 'en' ? 'en-US' : 'ja-JP';
    const source = sourceConfig[sourceKey];
    const state = stateConfig[status.state];

    return (
        <article className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-slate-900">
                            {t(`weeklyImport.sources.${sourceKey}.label`)}
                        </h3>
                        <span
                            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${state.badge}`}
                        >
                            {t(`weeklyImport.state.${status.state}`)}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-slate-600">
                        {t(`weeklyImport.sources.${sourceKey}.description`)}
                    </p>
                </div>

                <Link
                    href={route('imports.create', { source: source.source })}
                    className="inline-flex shrink-0 items-center justify-center rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    {status.state === 'updated'
                        ? t('weeklyImport.actions.reimport')
                        : t('weeklyImport.actions.update')}
                </Link>
            </div>

            <dl className="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt className="text-xs font-medium text-slate-500">
                        {t('weeklyImport.lastUpdated')}
                    </dt>
                    <dd className="mt-1 font-medium text-slate-800">
                        {formatDateTime(
                            status.last_updated_at,
                            locale,
                            t('weeklyImport.never'),
                        )}
                    </dd>
                </div>
                <div>
                    <dt className="text-xs font-medium text-slate-500">
                        {t('weeklyImport.latestHistoryDate')}
                    </dt>
                    <dd className="mt-1 font-medium text-slate-800">
                        {status.latest_history_date
                            ? formatDate(status.latest_history_date, locale)
                            : t('weeklyImport.noHistory')}
                    </dd>
                </div>
            </dl>
        </article>
    );
}

export default function WeeklyImportStatusCard({
    status,
}: WeeklyImportStatusCardProps) {
    const { t, i18n } = useTranslation('dashboard');
    const locale = i18n.resolvedLanguage === 'en' ? 'en-US' : 'ja-JP';

    return (
        <section className="rounded-2xl border border-sky-200 bg-sky-50/60 p-5">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">
                    {t('weeklyImport.title')}
                </h2>
                <p className="mt-1 text-sm text-slate-600">
                    {t('weeklyImport.description', {
                        start: formatDate(status.week_start, locale),
                        end: formatDate(status.week_end, locale),
                    })}
                </p>
            </div>

            <div className="mt-4 grid gap-4 xl:grid-cols-2">
                <SourceStatus
                    sourceKey="jre_point"
                    status={status.sources.jre_point}
                />
                <SourceStatus
                    sourceKey="mobile_suica"
                    status={status.sources.mobile_suica}
                />
            </div>
        </section>
    );
}
