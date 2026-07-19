import AppPage from '@/Components/AppPage';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { PageProps } from '@/types';
import { formatMoney } from '@/utils/currency';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { useTranslation } from 'react-i18next';

type ReconciliationAccount = {
    id: number;
    name: string;
    type: string;
    type_label: string;
    balance_role: 'asset' | 'liability' | 'clearing';
    balance_role_label: string;
    balance_method: 'ledger' | 'snapshot';
    include_in_net_worth: boolean;
    currency: string;
    initial_balance: string;
    opening_balance_date: string | null;
    current_balance: string;
    latest_snapshot_date: string | null;
    latest_snapshot_balance: string | null;
    latest_official_balance_date: string | null;
    latest_official_balance: string | null;
    latest_official_balance_source: string | null;
    next_payment_amount: string | null;
    next_payment_date: string | null;
};

type IndexProps = {
    balanceDate: string;
    reconcilableAccounts: ReconciliationAccount[];
    snapshotAccounts: ReconciliationAccount[];
    clearingAccounts: ReconciliationAccount[];
};

function toMinorUnits(value: string): number | null {
    if (value.trim() === '') {
        return null;
    }

    const amount = Number(value);

    return Number.isFinite(amount) ? Math.round(amount * 100) : null;
}

function ReconciliationCard({
    account,
    balanceDate,
}: {
    account: ReconciliationAccount;
    balanceDate: string;
}) {
    const { t } = useTranslation('accounts');
    const { data, setData, post, processing, errors, reset } = useForm({
        balance_date: balanceDate,
        actual_balance: account.latest_official_balance ?? '',
        source_name:
            account.latest_official_balance_source ??
            t('defaults.manualReconciliation'),
        note: '',
        confirmed: true,
    });
    const actualMinor = toMinorUnits(data.actual_balance);
    const currentMinor = toMinorUnits(account.current_balance) ?? 0;
    const initialMinor = toMinorUnits(account.initial_balance) ?? 0;
    const differenceMinor = actualMinor === null ? null : actualMinor - currentMinor;
    const suggestedInitialMinor =
        differenceMinor === null ? null : initialMinor + differenceMinor;
    const officialMinor = account.latest_official_balance
        ? toMinorUnits(account.latest_official_balance)
        : null;
    const officialDifferenceMinor =
        officialMinor === null ? null : officialMinor - currentMinor;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (differenceMinor === null) {
            return;
        }

        const difference = formatMoney(
            differenceMinor / 100,
            account.currency,
        );

        if (
            ! window.confirm(
                t('reconciliation.confirmAdjustment', {
                    name: account.name,
                    difference,
                    currency: account.currency,
                }),
            )
        ) {
            return;
        }

        post(route('accounts.reconciliation.store', account.id), {
            preserveScroll: true,
            onSuccess: () => reset('actual_balance', 'note'),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-xl border border-slate-200 bg-white p-5"
        >
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-semibold text-slate-900">
                            {account.name}
                        </h3>
                        <span
                            className={`rounded-full px-2.5 py-1 text-xs font-semibold ${
                                account.balance_role === 'liability'
                                    ? 'bg-rose-100 text-rose-700'
                                    : 'bg-emerald-100 text-emerald-700'
                            }`}
                        >
                            {account.balance_role_label}
                        </span>
                        {! account.include_in_net_worth ? (
                            <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-600">
                                {t('reconciliation.excluded')}
                            </span>
                        ) : null}
                    </div>
                    <p className="mt-1 text-xs text-slate-500">
                        {account.type_label}・{account.currency}
                    </p>
                </div>
                <div className="text-right">
                    <p className="text-xs text-slate-500">
                        {t('reconciliation.ledgerBalance')}
                    </p>
                    <p className="text-lg font-semibold text-slate-900">
                        {formatMoney(account.current_balance, account.currency)}{' '}
                        <span className="text-xs font-normal text-slate-500">
                            {account.currency}
                        </span>
                    </p>
                </div>
            </div>

            <div className="mt-4 grid gap-4 md:grid-cols-2">
                <div>
                    <InputLabel
                        htmlFor={`actual-balance-${account.id}`}
                        value={
                            account.balance_role === 'liability'
                                ? t('reconciliation.actualLiability')
                                : t('reconciliation.actualBalance')
                        }
                    />
                    <TextInput
                        id={`actual-balance-${account.id}`}
                        type="number"
                        step="0.01"
                        value={data.actual_balance}
                        onChange={(event) =>
                            setData('actual_balance', event.target.value)
                        }
                        className="mt-1 block w-full"
                        placeholder={
                            account.balance_role === 'liability'
                                ? '-65940'
                                : '407242'
                        }
                        required
                    />
                    <InputError
                        message={errors.actual_balance}
                        className="mt-2"
                    />
                </div>
                <div>
                    <InputLabel
                        htmlFor={`source-name-${account.id}`}
                        value={t('reconciliation.source')}
                    />
                    <TextInput
                        id={`source-name-${account.id}`}
                        value={data.source_name}
                        onChange={(event) =>
                            setData('source_name', event.target.value)
                        }
                        className="mt-1 block w-full"
                        placeholder={t('reconciliation.sourcePlaceholder')}
                    />
                    <InputError
                        message={errors.source_name}
                        className="mt-2"
                    />
                </div>
            </div>

            {account.latest_official_balance_date && officialMinor !== null ? (
                <div className="mt-4 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p className="font-semibold">
                                {t('reconciliation.officialBalance')}
                            </p>
                            <p className="mt-1 text-xs text-sky-800">
                                {account.latest_official_balance_date}
                                {account.latest_official_balance_source
                                    ? ` / ${account.latest_official_balance_source}`
                                    : ''}
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="font-semibold">
                                {formatMoney(
                                    officialMinor / 100,
                                    account.currency,
                                )}{' '}
                                {account.currency}
                            </p>
                            <p className="mt-1 text-xs text-sky-800">
                                {t('reconciliation.differenceFromLedger', {
                                    difference: formatMoney(
                                        (officialDifferenceMinor ?? 0) / 100,
                                        account.currency,
                                    ),
                                })}
                            </p>
                        </div>
                    </div>
                    {account.next_payment_amount || account.next_payment_date ? (
                        <p className="mt-3 border-t border-sky-200 pt-3 text-xs text-sky-800">
                            {t('reconciliation.nextPayment')}{' '}
                            {account.next_payment_amount
                                ? `${formatMoney(account.next_payment_amount, account.currency)} ${account.currency}`
                                : t('reconciliation.amountUnavailable')}
                            {account.next_payment_date
                                ? ` / ${account.next_payment_date}`
                                : ''}
                        </p>
                    ) : null}
                </div>
            ) : null}

            <div className="mt-4 grid gap-3 rounded-lg bg-slate-50 p-4 text-sm md:grid-cols-3">
                <div>
                    <p className="text-xs text-slate-500">
                        {t('reconciliation.currentOpeningBalance')}
                    </p>
                    <p className="mt-1 font-medium text-slate-800">
                        {formatMoney(account.initial_balance, account.currency)}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">
                        {t('reconciliation.difference')}
                    </p>
                    <p
                        className={`mt-1 font-medium ${
                            differenceMinor === 0
                                ? 'text-emerald-700'
                                : 'text-amber-700'
                        }`}
                    >
                        {differenceMinor === null
                            ? t('reconciliation.enterActualBalance')
                            : formatMoney(
                                  differenceMinor / 100,
                                  account.currency,
                              )}
                    </p>
                </div>
                <div>
                    <p className="text-xs text-slate-500">
                        {t('reconciliation.adjustedOpeningBalance')}
                    </p>
                    <p className="mt-1 font-medium text-slate-800">
                        {suggestedInitialMinor === null
                            ? '—'
                            : formatMoney(
                                  suggestedInitialMinor / 100,
                                  account.currency,
                              )}
                    </p>
                </div>
            </div>

            {account.latest_snapshot_date ? (
                <p className="mt-3 text-xs text-slate-500">
                    {t('reconciliation.previousReconciliation', {
                        date: account.latest_snapshot_date,
                        balance: formatMoney(
                            account.latest_snapshot_balance ?? '0',
                            account.currency,
                        ),
                        currency: account.currency,
                    })}
                </p>
            ) : null}

            <div className="mt-4 flex flex-wrap items-end justify-between gap-4">
                <div className="min-w-[240px] flex-1">
                    <InputLabel
                        htmlFor={`note-${account.id}`}
                        value={t('reconciliation.optionalNote')}
                    />
                    <TextInput
                        id={`note-${account.id}`}
                        value={data.note}
                        onChange={(event) => setData('note', event.target.value)}
                        className="mt-1 block w-full"
                        placeholder={t('reconciliation.notePlaceholder')}
                    />
                </div>
                <PrimaryButton
                    type="submit"
                    disabled={processing || differenceMinor === null}
                >
                    {t('reconciliation.applyDifference')}
                </PrimaryButton>
            </div>
        </form>
    );
}

export default function Index({
    balanceDate,
    reconcilableAccounts,
    snapshotAccounts,
    clearingAccounts,
}: IndexProps) {
    const { t } = useTranslation('accounts');
    const flashSuccess = usePage<PageProps>().props.flash.success;
    const [selectedDate, setSelectedDate] = useState(balanceDate);

    const updateDate = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('accounts.reconciliation.index'),
            { balance_date: selectedDate },
            { preserveState: false },
        );
    };

    return (
        <AppPage
            title={t('reconciliation.title')}
            description={t('reconciliation.description')}
        >
            <div className="space-y-8">
                {flashSuccess ? (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flashSuccess}
                    </div>
                ) : null}

                <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <p className="font-semibold">
                        {t('reconciliation.warningTitle')}
                    </p>
                    <p className="mt-1 leading-6">
                        {t('reconciliation.warningDescription')}
                    </p>
                </div>

                <div className="flex flex-wrap items-end justify-between gap-4">
                    <form onSubmit={updateDate} className="flex items-end gap-3">
                        <div>
                            <InputLabel
                                htmlFor="balance-date"
                                value={t('reconciliation.date')}
                            />
                            <TextInput
                                id="balance-date"
                                type="date"
                                value={selectedDate}
                                max={new Date().toISOString().slice(0, 10)}
                                onChange={(event) =>
                                    setSelectedDate(event.target.value)
                                }
                                className="mt-1"
                                required
                            />
                        </div>
                        <PrimaryButton type="submit">
                            {t('reconciliation.recalculate')}
                        </PrimaryButton>
                    </form>
                    <div className="flex flex-wrap items-center gap-4">
                        <Link
                            href={route('imports.create', {
                                source: 'balance_snapshot',
                            })}
                            className="rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-800 hover:bg-indigo-100"
                        >
                            {t('reconciliation.importOfficial')}
                        </Link>
                        <Link
                            href={route('accounts.index')}
                            className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            {t('actions.backToAccounts')}
                        </Link>
                    </div>
                </div>

                <section>
                    <div className="mb-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t('reconciliation.ledgerAccounts')}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t('reconciliation.ledgerAccountsDescription')}
                        </p>
                    </div>
                    <div className="space-y-4">
                        {reconcilableAccounts.map((account) => (
                            <ReconciliationCard
                                key={`${account.id}-${balanceDate}`}
                                account={account}
                                balanceDate={balanceDate}
                            />
                        ))}
                    </div>
                </section>

                {snapshotAccounts.length > 0 ? (
                    <section>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {t('reconciliation.valuationAccounts')}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t('reconciliation.valuationAccountsDescription')}
                        </p>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            {snapshotAccounts.map((account) => (
                                <div
                                    key={account.id}
                                    className="rounded-xl border border-emerald-200 bg-emerald-50 p-5"
                                >
                                    <div className="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 className="font-semibold text-slate-900">
                                                {account.name}
                                            </h3>
                                            <p className="mt-1 text-xs text-slate-600">
                                                {account.latest_snapshot_date
                                                    ? t('reconciliation.latestValuation', {
                                                          date: account.latest_snapshot_date,
                                                      })
                                                    : t('reconciliation.valuationMissing')}
                                            </p>
                                        </div>
                                        <Link
                                            href={route(
                                                'accounts.snapshots.index',
                                                account.id,
                                            )}
                                            className="rounded-md border border-emerald-300 bg-white px-3 py-2 text-xs font-semibold text-emerald-800 hover:bg-emerald-100"
                                        >
                                            {t('reconciliation.recordValuation')}
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                {clearingAccounts.length > 0 ? (
                    <section className="rounded-xl border border-slate-200 bg-slate-50 p-5">
                        <h2 className="font-semibold text-slate-900">
                            {t('reconciliation.clearingAccounts')}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {t('reconciliation.clearingAccountsDescription')}
                        </p>
                        <div className="mt-3 flex flex-wrap gap-2">
                            {clearingAccounts.map((account) => (
                                <span
                                    key={account.id}
                                    className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-700"
                                >
                                    {account.name}:{' '}
                                    {formatMoney(
                                        account.current_balance,
                                        account.currency,
                                    )}{' '}
                                    {account.currency}
                                </span>
                            ))}
                        </div>
                    </section>
                ) : null}
            </div>
        </AppPage>
    );
}
