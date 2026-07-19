import { DashboardDailySnapshotStatus } from '@/types/dashboard';
import { Link } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

type DailySnapshotStatusCardProps = {
    status: DashboardDailySnapshotStatus;
};

const stateStyle = {
    complete: {
        badge: 'bg-emerald-100 text-emerald-800',
        border: 'border-emerald-200 bg-emerald-50/60',
    },
    partial: {
        badge: 'bg-amber-100 text-amber-800',
        border: 'border-amber-200 bg-amber-50/60',
    },
    missing: {
        badge: 'bg-rose-100 text-rose-800',
        border: 'border-rose-200 bg-rose-50/60',
    },
} as const;

const accountStateStyle = {
    updated: {
        badge: 'bg-emerald-100 text-emerald-800',
    },
    stale: {
        badge: 'bg-amber-100 text-amber-800',
    },
} as const;

const coverageStateStyle = {
    complete: {
        card: 'border-emerald-200 bg-emerald-50',
        badge: 'bg-emerald-100 text-emerald-800',
    },
    partial: {
        card: 'border-amber-200 bg-amber-50',
        badge: 'bg-amber-100 text-amber-800',
    },
    missing: {
        card: 'border-slate-200 bg-slate-50',
        badge: 'bg-slate-200 text-slate-700',
    },
    not_required: {
        card: 'border-slate-200 bg-slate-50/70',
        badge: 'bg-slate-100 text-slate-600',
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

const formatCoverageDate = (value: string, locale: string) =>
    new Intl.DateTimeFormat(locale, {
        month: 'numeric',
        day: 'numeric',
        weekday: 'short',
    }).format(new Date(`${value}T00:00:00`));

const accountHref = (
    account: DashboardDailySnapshotStatus['accounts'][number],
) =>
    account.type === 'securities'
        ? route('securities.show', account.id)
        : route('accounts.snapshots.index', account.id);

export default function DailySnapshotStatusCard({
    status,
}: DailySnapshotStatusCardProps) {
    const { t, i18n } = useTranslation('dashboard');
    const { t: tAccounts } = useTranslation('accounts');
    const locale = i18n.resolvedLanguage === 'en' ? 'en-US' : 'ja-JP';
    const style = stateStyle[status.state];

    return (
        <section className={`rounded-2xl border p-5 ${style.border}`}>
            <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-3">
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t('dailySnapshot.title')}
                        </h2>
                        <span
                            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ${style.badge}`}
                        >
                            {t(`dailySnapshot.state.${status.state}`)}
                        </span>
                    </div>
                    <p className="mt-2 text-sm text-slate-600">
                        {t('dailySnapshot.description')}
                    </p>
                </div>

                <Link
                    href={route('imports.create', {
                        source: 'balance_snapshot',
                    })}
                    className="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-slate-700"
                >
                    {status.state === 'complete'
                        ? t('dailySnapshot.actions.reacquire')
                        : t('dailySnapshot.actions.acquire')}
                </Link>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">
                        {t('dailySnapshot.summary.date')}
                    </dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {formatDate(status.date, locale)}
                    </dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">
                        {t('dailySnapshot.summary.accounts')}
                    </dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {t('dailySnapshot.summary.accountCount', {
                            updated: status.updated_account_count,
                            required: status.required_account_count,
                        })}
                    </dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">
                        {t('dailySnapshot.summary.positions')}
                    </dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {t('dailySnapshot.summary.positionCount', {
                            count: status.position_count,
                        })}
                    </dd>
                </div>
                <div className="rounded-xl border border-white/80 bg-white/80 px-4 py-3">
                    <dt className="text-xs font-medium text-slate-500">
                        {t('dailySnapshot.summary.totalAssets')}
                    </dt>
                    <dd className="mt-1 font-semibold text-slate-900">
                        {status.asset_history_recorded
                            ? t('dailySnapshot.summary.recorded')
                            : t('dailySnapshot.summary.unrecorded')}
                    </dd>
                </div>
            </dl>

            <p className="mt-3 text-xs text-slate-500">
                {t('dailySnapshot.summary.latestImport', {
                    count: status.account_count,
                    date: formatDateTime(
                        status.last_imported_at,
                        locale,
                        t('dailySnapshot.never'),
                    ),
                })}
            </p>

            <div className="mt-5 rounded-xl border border-white/80 bg-white/80 p-4">
                <div>
                    <h3 className="text-sm font-semibold text-slate-900">
                        {t('dailySnapshot.coverage.title')}
                    </h3>
                    <p className="mt-1 text-xs text-slate-500">
                        {status.coverage_started_on
                            ? t('dailySnapshot.coverage.started', {
                                  date: formatDate(
                                      status.coverage_started_on,
                                      locale,
                                  ),
                              })
                            : t('dailySnapshot.coverage.notStarted')}
                    </p>
                </div>
                <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-7">
                    {status.coverage_days.map((day) => {
                        const coverageStyle = coverageStateStyle[day.state];

                        return (
                            <div
                                key={day.date}
                                className={`rounded-lg border px-3 py-2.5 ${coverageStyle.card}`}
                            >
                                <div className="flex flex-wrap items-center justify-between gap-1">
                                    <p className="text-xs font-semibold text-slate-800">
                                        {formatCoverageDate(day.date, locale)}
                                    </p>
                                    <span
                                        className={`rounded-full px-2 py-0.5 text-[10px] font-semibold ${coverageStyle.badge}`}
                                    >
                                        {t(
                                            `dailySnapshot.coverageState.${day.state}`,
                                        )}
                                    </span>
                                </div>
                                {day.state === 'not_required' ? (
                                    <p className="mt-3 text-[11px] leading-5 text-slate-500">
                                        {t(
                                            'dailySnapshot.coverage.beforeStartLine1',
                                        )}
                                        <br />
                                        {t(
                                            'dailySnapshot.coverage.beforeStartLine2',
                                        )}
                                    </p>
                                ) : (
                                    <dl className="mt-2 space-y-1 text-[11px] text-slate-600">
                                        <div className="flex justify-between gap-2">
                                            <dt>
                                                {t(
                                                    'dailySnapshot.coverage.accounts',
                                                )}
                                            </dt>
                                            <dd className="font-medium text-slate-800">
                                                {day.updated_account_count}/
                                                {day.required_account_count}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt>
                                                {t(
                                                    'dailySnapshot.coverage.positions',
                                                )}
                                            </dt>
                                            <dd className="font-medium text-slate-800">
                                                {day.position_count}
                                            </dd>
                                        </div>
                                        <div className="flex justify-between gap-2">
                                            <dt>
                                                {t(
                                                    'dailySnapshot.coverage.totalAssets',
                                                )}
                                            </dt>
                                            <dd className="font-medium text-slate-800">
                                                {day.asset_history_recorded
                                                    ? t(
                                                          'dailySnapshot.summary.recorded',
                                                      )
                                                    : t(
                                                          'dailySnapshot.summary.unrecorded',
                                                      )}
                                            </dd>
                                        </div>
                                    </dl>
                                )}
                            </div>
                        );
                    })}
                </div>
            </div>

            {status.accounts.length > 0 ? (
                <div className="mt-5 overflow-hidden rounded-xl border border-white/80 bg-white/80">
                    <div className="border-b border-slate-200 px-4 py-3">
                        <h3 className="text-sm font-semibold text-slate-900">
                            {t('dailySnapshot.accounts.title')}
                        </h3>
                        <p className="mt-1 text-xs text-slate-500">
                            {t('dailySnapshot.accounts.description')}
                        </p>
                    </div>
                    <ul className="divide-y divide-slate-200">
                        {status.accounts.map((account) => {
                            const accountState =
                                accountStateStyle[account.state];

                            return (
                                <li key={account.id}>
                                    <Link
                                        href={accountHref(account)}
                                        className="flex flex-col gap-2 px-4 py-3 hover:bg-slate-50 sm:flex-row sm:items-center sm:justify-between"
                                    >
                                        <div>
                                            <span className="font-medium text-slate-900">
                                                {account.name}
                                            </span>
                                            <span className="ml-2 text-xs text-slate-500">
                                                {tAccounts(
                                                    `types.${account.type}`,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-3">
                                            <span className="text-xs text-slate-500">
                                                {t(
                                                    'dailySnapshot.accounts.snapshotDate',
                                                    {
                                                        date: formatDate(
                                                            account.latest_snapshot_date,
                                                            locale,
                                                        ),
                                                    },
                                                )}
                                            </span>
                                            <span
                                                className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${accountState.badge}`}
                                            >
                                                {t(
                                                    `dailySnapshot.accountState.${account.state}`,
                                                )}
                                            </span>
                                        </div>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                </div>
            ) : null}

            {status.recent_failures.length > 0 ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h3 className="text-sm font-semibold text-rose-900">
                                {t('dailySnapshot.failures.title')}
                            </h3>
                            <p className="mt-1 text-xs text-rose-700">
                                {t('dailySnapshot.failures.description')}
                            </p>
                        </div>
                        <Link
                            href={route('imports.index')}
                            className="text-sm font-semibold text-rose-800 underline decoration-rose-300 underline-offset-4 hover:text-rose-950"
                        >
                            {t('dailySnapshot.failures.history')}
                        </Link>
                    </div>
                    <ul className="mt-3 space-y-2">
                        {status.recent_failures.map((failure) => (
                            <li
                                key={failure.id}
                                className="text-sm text-rose-900"
                            >
                                <Link
                                    href={route('imports.show', failure.id)}
                                    className="font-semibold underline decoration-rose-300 underline-offset-4"
                                >
                                    {t(
                                        `dailySnapshot.failures.sources.${failure.source_name}`,
                                        { defaultValue: failure.source_name },
                                    )}
                                </Link>
                                <span className="ml-2 text-xs text-rose-700">
                                    {formatDateTime(
                                        failure.failed_at,
                                        locale,
                                        t('dailySnapshot.never'),
                                    )}{' '}
                                    · {failure.original_filename}
                                </span>
                                {failure.error_message ? (
                                    <p className="mt-1 line-clamp-2 text-xs text-rose-700">
                                        {failure.error_message}
                                    </p>
                                ) : null}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : null}
        </section>
    );
}
