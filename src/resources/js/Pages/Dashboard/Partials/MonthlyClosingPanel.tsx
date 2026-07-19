import { DashboardMonthlyClosing } from '@/types/dashboard';
import { Link, router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { FormEvent, useEffect, useState } from 'react';

type MonthlyClosingPanelProps = {
    closing: DashboardMonthlyClosing;
    selectedMonth: string;
};

const formatDateTime = (value: string | null, locale: string) => {
    if (value === null) {
        return null;
    }

    return new Intl.DateTimeFormat(locale, {
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const statusTone = (status: DashboardMonthlyClosing['status']) => {
    if (status === 'closed') {
        return 'bg-emerald-100 text-emerald-800';
    }

    if (status === 'reviewed') {
        return 'bg-indigo-100 text-indigo-800';
    }

    return 'bg-amber-100 text-amber-800';
};

const getMonthDateRange = (month: string) => {
    const [yearValue, monthValue] = month.split('-').map(Number);
    const lastDay = new Date(yearValue, monthValue, 0).getDate();

    return {
        date_from: `${month}-01`,
        date_to: `${month}-${String(lastDay).padStart(2, '0')}`,
    };
};

const accountConfirmationGuideKey = (type: string) => {
    if (type === 'credit_card') {
        return 'monthlyReport.closing.accounts.guides.creditCard';
    }

    if (type === 'securities') {
        return 'monthlyReport.closing.accounts.guides.securities';
    }

    if (type === 'bank') {
        return 'monthlyReport.closing.accounts.guides.bank';
    }

    if (type === 'e_money') {
        return 'monthlyReport.closing.accounts.guides.eMoney';
    }

    if (type === 'point') {
        return 'monthlyReport.closing.accounts.guides.point';
    }

    return 'monthlyReport.closing.accounts.guides.default';
};

const InfoTooltip = ({
    id,
    label,
    children,
}: {
    id: string;
    label: string;
    children: string;
}) => (
    <span className="group relative inline-flex shrink-0">
        <button
            type="button"
            aria-label={label}
            aria-describedby={id}
            className="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-300 bg-white text-[11px] font-bold text-slate-500 transition hover:border-indigo-300 hover:text-indigo-700 focus:border-indigo-400 focus:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-200"
        >
            ?
        </button>
        <span
            id={id}
            role="tooltip"
            className="pointer-events-none invisible absolute left-1/2 top-7 z-30 w-72 -translate-x-1/2 rounded-xl bg-slate-900 px-3 py-2.5 text-left text-xs font-normal leading-5 text-white opacity-0 shadow-xl transition group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100 sm:left-0 sm:translate-x-0"
        >
            {children}
        </span>
    </span>
);

export default function MonthlyClosingPanel({
    closing,
    selectedMonth,
}: MonthlyClosingPanelProps) {
    const { t, i18n } = useTranslation('dashboard');
    const dateLocale = i18n.language.startsWith('en') ? 'en-US' : 'ja-JP';
    const errors = usePage().props.errors as Record<string, string>;
    const [note, setNote] = useState(closing.note);
    const [processing, setProcessing] = useState(false);
    const [showReopen, setShowReopen] = useState(false);
    const [showClosedAccountDetails, setShowClosedAccountDetails] =
        useState(false);
    const [reopenReason, setReopenReason] = useState('');
    const range = getMonthDateRange(selectedMonth);
    const hasChangedAccounts = closing.accounts.some(
        (account) => account.state === 'changed',
    );
    const shouldShowAccountDetails =
        closing.status !== 'closed' ||
        closing.has_changes_since_review ||
        hasChangedAccounts ||
        showClosedAccountDetails;

    useEffect(() => {
        setNote(closing.note);
        setShowReopen(false);
        setShowClosedAccountDetails(false);
        setReopenReason('');
    }, [closing.note, selectedMonth]);

    const options = {
        preserveScroll: true,
        onStart: () => setProcessing(true),
        onFinish: () => setProcessing(false),
    };

    const saveNote = (event: FormEvent) => {
        event.preventDefault();
        router.patch(
            route('monthly-closings.update', selectedMonth),
            { note: note.trim() === '' ? null : note },
            options,
        );
    };

    const review = () => {
        router.post(
            route('monthly-closings.review', selectedMonth),
            {},
            options,
        );
    };

    const close = () => {
        if (
            !window.confirm(
                t('monthlyReport.closing.closeConfirm', {
                    month: selectedMonth,
                }),
            )
        ) {
            return;
        }

        router.post(
            route('monthly-closings.close', selectedMonth),
            {},
            options,
        );
    };

    const reopen = (event: FormEvent) => {
        event.preventDefault();

        router.post(
            route('monthly-closings.reopen', selectedMonth),
            { reason: reopenReason },
            {
                ...options,
                onSuccess: () => {
                    setShowReopen(false);
                    setReopenReason('');
                },
            },
        );
    };

    const toggleAccount = (
        account: DashboardMonthlyClosing['accounts'][number],
    ) => {
        const accountRoute = route(
            account.state === 'confirmed'
                ? 'monthly-closings.accounts.unconfirm'
                : 'monthly-closings.accounts.confirm',
            { month: selectedMonth, account: account.id },
        );

        if (account.state === 'confirmed') {
            router.delete(accountRoute, options);
        } else {
            router.put(accountRoute, {}, options);
        }
    };

    return (
        <article className="rounded-2xl border border-indigo-200 bg-white p-4 shadow-sm sm:p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-slate-900">
                            {t('monthlyReport.closing.title')}
                        </h3>
                        <InfoTooltip
                            id="monthly-report-review-help"
                            label={t('monthlyReport.closing.reviewHelpLabel')}
                        >
                            {t('monthlyReport.closing.reviewHelp')}
                        </InfoTooltip>
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusTone(closing.status)}`}
                        >
                            {closing.status_label}
                        </span>
                        {closing.has_changes_since_review ? (
                            <span className="rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-800">
                                {t('monthlyReport.closing.changedBadge')}
                            </span>
                        ) : null}
                    </div>
                    <p className="mt-1 text-sm leading-6 text-slate-500">
                        {t('monthlyReport.closing.description')}
                    </p>
                    {closing.closed_at ? (
                        <p className="mt-1 text-xs text-slate-500">
                            {t('monthlyReport.closing.closedAt', {
                                date: formatDateTime(
                                    closing.closed_at,
                                    dateLocale,
                                ),
                            })}
                        </p>
                    ) : closing.reviewed_at ? (
                        <p className="mt-1 text-xs text-slate-500">
                            {t('monthlyReport.closing.reviewedAt', {
                                date: formatDateTime(
                                    closing.reviewed_at,
                                    dateLocale,
                                ),
                            })}
                        </p>
                    ) : null}
                </div>

                {closing.status === 'closed' ? (
                    <button
                        type="button"
                        onClick={() => setShowReopen((current) => !current)}
                        disabled={processing}
                        className="rounded-full border border-rose-200 bg-white px-4 py-2 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:opacity-50"
                    >
                        {t('monthlyReport.closing.reopen')}
                    </button>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            onClick={review}
                            disabled={processing}
                            className="rounded-full border border-indigo-200 bg-white px-4 py-2 text-sm font-medium text-indigo-700 transition hover:bg-indigo-50 disabled:opacity-50"
                        >
                            {closing.status === 'reviewed' ||
                            closing.has_changes_since_review
                                ? t('monthlyReport.closing.reviewAgain')
                                : t('monthlyReport.closing.markReviewed')}
                        </button>
                        <button
                            type="button"
                            onClick={close}
                            disabled={processing || !closing.can_close}
                            className="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            {t('monthlyReport.closing.close')}
                        </button>
                    </div>
                )}
            </div>

            {errors.monthly_closing || errors.account || errors.reason ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {errors.monthly_closing ?? errors.account ?? errors.reason}
                </div>
            ) : null}

            {closing.has_changes_since_review ? (
                <div className="mt-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm leading-6 text-rose-800">
                    {t('monthlyReport.closing.changedNotice')}
                </div>
            ) : null}

            <div className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(280px,0.65fr)]">
                <section>
                    <div className="flex items-start justify-between gap-3">
                        <div>
                            <div className="flex items-center gap-2">
                                <h4 className="text-sm font-semibold text-slate-800">
                                    {t('monthlyReport.closing.accounts.title')}
                                </h4>
                                <InfoTooltip
                                    id="account-confirmation-help"
                                    label={t(
                                        'monthlyReport.closing.accounts.helpLabel',
                                    )}
                                >
                                    {t('monthlyReport.closing.accounts.help')}
                                </InfoTooltip>
                            </div>
                            <p className="mt-1 text-xs leading-5 text-slate-500">
                                {t(
                                    'monthlyReport.closing.accounts.description',
                                )}
                            </p>
                        </div>
                        <div className="flex shrink-0 flex-col items-end gap-1">
                            <span className="text-xs text-slate-500">
                                {t('monthlyReport.closing.accounts.count', {
                                    confirmed: closing.accounts.filter(
                                        (account) =>
                                            account.state === 'confirmed',
                                    ).length,
                                    total: closing.accounts.length,
                                })}
                            </span>
                            {closing.status === 'closed' &&
                            !closing.has_changes_since_review &&
                            !hasChangedAccounts ? (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setShowClosedAccountDetails(
                                            (current) => !current,
                                        )
                                    }
                                    className="text-xs font-medium text-indigo-700 underline decoration-indigo-200 underline-offset-2 hover:text-indigo-900"
                                >
                                    {showClosedAccountDetails
                                        ? t(
                                              'monthlyReport.closing.accounts.hideDetails',
                                          )
                                        : t(
                                              'monthlyReport.closing.accounts.showDetails',
                                          )}
                                </button>
                            ) : null}
                        </div>
                    </div>

                    {!shouldShowAccountDetails ? (
                        <div className="mt-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-4">
                            <p className="text-sm font-medium text-emerald-900">
                                {t(
                                    'monthlyReport.closing.accounts.closedSummary',
                                    { count: closing.accounts.length },
                                )}
                            </p>
                            <p className="mt-1 text-xs leading-5 text-emerald-700">
                                {t(
                                    'monthlyReport.closing.accounts.closedSummaryHint',
                                )}
                            </p>
                        </div>
                    ) : closing.accounts.length === 0 ? (
                        <div className="mt-3 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-4 text-sm leading-6 text-amber-800">
                            {t('monthlyReport.closing.accounts.empty')}
                        </div>
                    ) : (
                        <ul className="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200">
                            {closing.accounts.map((account) => (
                                <li
                                    key={account.id}
                                    className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                                >
                                    <div className="min-w-0 flex-1">
                                        <Link
                                            href={route('transactions.index', {
                                                ...range,
                                                account_id: account.id,
                                                calculation_target: 'all',
                                            })}
                                            title={t(
                                                'monthlyReport.closing.accounts.transactionsTitle',
                                                {
                                                    account: account.name,
                                                    month: selectedMonth,
                                                },
                                            )}
                                            className="inline-flex items-center gap-1 font-medium text-indigo-700 underline decoration-indigo-200 underline-offset-4 transition hover:text-indigo-900 hover:decoration-indigo-500"
                                        >
                                            <span>{account.name}</span>
                                            <span aria-hidden="true">→</span>
                                        </Link>
                                        <p className="mt-0.5 text-xs text-slate-500">
                                            {account.currency}
                                            {account.confirmed_at
                                                ? ` · ${t(
                                                      'monthlyReport.closing.accounts.confirmedAt',
                                                      {
                                                          date: formatDateTime(
                                                              account.confirmed_at,
                                                              dateLocale,
                                                          ),
                                                      },
                                                  )}`
                                                : ''}
                                        </p>
                                        <p className="mt-1 max-w-2xl text-xs leading-5 text-slate-600">
                                            <span className="font-medium text-slate-700">
                                                {t(
                                                    'monthlyReport.closing.accounts.guideLabel',
                                                )}
                                            </span>{' '}
                                            {t(
                                                accountConfirmationGuideKey(
                                                    account.type,
                                                ),
                                            )}
                                        </p>
                                        {account.state === 'changed' ? (
                                            <p className="mt-1 text-xs font-medium text-rose-700">
                                                {t(
                                                    'monthlyReport.closing.accounts.changed',
                                                )}
                                            </p>
                                        ) : null}
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => toggleAccount(account)}
                                        disabled={
                                            processing ||
                                            closing.status === 'closed'
                                        }
                                        className={`rounded-full px-3 py-1.5 text-xs font-semibold transition disabled:opacity-50 ${
                                            account.state === 'confirmed'
                                                ? 'border border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'
                                                : account.state === 'changed'
                                                  ? 'border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100'
                                                  : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100'
                                        }`}
                                    >
                                        {account.state === 'confirmed'
                                            ? t(
                                                  'monthlyReport.closing.accounts.unconfirm',
                                              )
                                            : account.state === 'changed'
                                              ? t(
                                                    'monthlyReport.closing.accounts.reconfirm',
                                                )
                                              : t(
                                                    'monthlyReport.closing.accounts.confirm',
                                                )}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="space-y-4">
                    {closing.status !== 'closed' ||
                    closing.has_changes_since_review ? (
                        <div>
                            <h4 className="text-sm font-semibold text-slate-800">
                                {t('monthlyReport.closing.blockers.title')}
                            </h4>
                            {closing.blockers.length === 0 ? (
                                <p className="mt-2 rounded-xl bg-emerald-50 px-3 py-2 text-sm text-emerald-800">
                                    {t(
                                        'monthlyReport.closing.blockers.complete',
                                    )}
                                </p>
                            ) : (
                                <ul className="mt-2 space-y-1.5 text-sm leading-5 text-slate-600">
                                    {closing.blockers.map((blocker) => (
                                        <li
                                            key={blocker}
                                            className="flex gap-2"
                                        >
                                            <span className="text-amber-500">
                                                •
                                            </span>
                                            <span>{blocker}</span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    ) : null}

                    <form onSubmit={saveNote}>
                        <label
                            htmlFor="monthly-closing-note"
                            className="text-sm font-semibold text-slate-800"
                        >
                            {t('monthlyReport.closing.note.label')}
                        </label>
                        <textarea
                            id="monthly-closing-note"
                            value={note}
                            onChange={(event) => setNote(event.target.value)}
                            rows={4}
                            maxLength={5000}
                            placeholder={t(
                                'monthlyReport.closing.note.placeholder',
                            )}
                            className="mt-2 block w-full rounded-xl border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        />
                        <div className="mt-2 flex justify-end">
                            <button
                                type="submit"
                                disabled={processing || note === closing.note}
                                className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                            >
                                {t('monthlyReport.closing.note.save')}
                            </button>
                        </div>
                    </form>
                </section>
            </div>

            {showReopen ? (
                <form
                    onSubmit={reopen}
                    className="mt-5 rounded-xl border border-rose-200 bg-rose-50 p-4"
                >
                    <label
                        htmlFor="monthly-closing-reopen-reason"
                        className="text-sm font-semibold text-rose-900"
                    >
                        {t('monthlyReport.closing.reopenForm.reason')}
                    </label>
                    <textarea
                        id="monthly-closing-reopen-reason"
                        value={reopenReason}
                        onChange={(event) =>
                            setReopenReason(event.target.value)
                        }
                        rows={2}
                        maxLength={1000}
                        required
                        className="mt-2 block w-full rounded-xl border-rose-200 text-sm shadow-sm focus:border-rose-400 focus:ring-rose-400"
                    />
                    <div className="mt-3 flex justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setShowReopen(false)}
                            className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700"
                        >
                            {t('monthlyReport.closing.reopenForm.cancel')}
                        </button>
                        <button
                            type="submit"
                            disabled={processing || reopenReason.trim() === ''}
                            className="rounded-full bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50"
                        >
                            {t('monthlyReport.closing.reopenForm.submit')}
                        </button>
                    </div>
                </form>
            ) : null}

            {closing.last_reopen_reason ? (
                <p className="mt-4 text-xs leading-5 text-slate-500">
                    {closing.last_reopened_at
                        ? t(
                              'monthlyReport.closing.reopenForm.previousWithDate',
                              {
                                  reason: closing.last_reopen_reason,
                                  date: formatDateTime(
                                      closing.last_reopened_at,
                                      dateLocale,
                                  ),
                              },
                          )
                        : t('monthlyReport.closing.reopenForm.previous', {
                              reason: closing.last_reopen_reason,
                          })}
                </p>
            ) : null}
        </article>
    );
}
