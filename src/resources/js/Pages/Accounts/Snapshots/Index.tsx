import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { PageProps } from '@/types';
import { formatMoney } from '@/utils/currency';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type SnapshotAccount = {
    id: number;
    name: string;
    currency: string;
    balance_method: string;
    has_valuation_snapshot: boolean;
    current_balance: string;
};

type ValuationSnapshot = {
    id: number;
    balance_date: string;
    balance: string;
    source_name: string | null;
    note: string | null;
};

type IndexProps = {
    account: SnapshotAccount;
    today: string;
    snapshots: ValuationSnapshot[];
};

type SnapshotFormValues = {
    balance_date: string;
    balance: string;
    source_name: string;
    note: string;
};

export default function Index({ account, today, snapshots }: IndexProps) {
    const { t } = useTranslation('accounts');
    const flashSuccess = usePage<PageProps>().props.flash.success;
    const [editingSnapshot, setEditingSnapshot] =
        useState<ValuationSnapshot | null>(null);
    const latestSnapshot = useMemo(() => snapshots[0] ?? null, [snapshots]);
    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm<SnapshotFormValues>({
            balance_date: today,
            balance: '',
            source_name: t('defaults.manualEntry'),
            note: '',
        });

    const stopEditing = () => {
        setEditingSnapshot(null);
        clearErrors();
        reset();
        setData('balance_date', today);
    };

    const startEditing = (snapshot: ValuationSnapshot) => {
        setEditingSnapshot(snapshot);
        clearErrors();
        setData({
            balance_date: snapshot.balance_date,
            balance: snapshot.balance,
            source_name: snapshot.source_name ?? t('defaults.manualEntry'),
            note: snapshot.note ?? '',
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: stopEditing,
        };

        if (editingSnapshot !== null) {
            put(
                route('accounts.snapshots.update', [
                    account.id,
                    editingSnapshot.id,
                ]),
                options,
            );

            return;
        }

        post(route('accounts.snapshots.store', account.id), options);
    };

    const handleDelete = (snapshot: ValuationSnapshot) => {
        if (
            !window.confirm(
                t('snapshots.confirmDelete', {
                    date: snapshot.balance_date,
                }),
            )
        ) {
            return;
        }

        router.delete(
            route('accounts.snapshots.destroy', [account.id, snapshot.id]),
            { preserveScroll: true },
        );
    };

    return (
        <AppPage
            title={t('snapshots.title', { name: account.name })}
            description={t('snapshots.description')}
        >
            <div className="space-y-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-2">
                        <Link
                            href={route('accounts.index')}
                            className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            {t('actions.backToAccounts')}
                        </Link>
                        <span className="text-slate-300">/</span>
                        <Link
                            href={route('accounts.edit', account.id)}
                            className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                        >
                            {t('actions.accountSettings')}
                        </Link>
                    </div>
                    <div className="text-right">
                        <p className="text-xs text-slate-500">
                            {account.has_valuation_snapshot
                                ? t('snapshots.currentWithSnapshot')
                                : t('snapshots.currentWithoutSnapshot')}
                        </p>
                        <p className="text-lg font-semibold text-slate-900">
                            {formatMoney(account.current_balance, account.currency)}{' '}
                            {account.currency}
                        </p>
                    </div>
                </div>

                {flashSuccess ? (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {flashSuccess}
                    </div>
                ) : null}

                {account.balance_method !== 'snapshot' ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                        {t('snapshots.methodWarning')}
                    </div>
                ) : null}

                {!account.has_valuation_snapshot ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm leading-6 text-amber-800">
                        {t('snapshots.missingWarning')}
                    </div>
                ) : null}

                <section className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <div className="mb-5">
                        <h2 className="font-semibold text-slate-900">
                            {editingSnapshot
                                ? t('snapshots.editTitle')
                                : t('snapshots.recordTitle')}
                        </h2>
                        <p className="mt-1 text-sm leading-6 text-slate-500">
                            {t('snapshots.formDescription')}
                        </p>
                    </div>

                    <form onSubmit={submit} className="space-y-5">
                        <div className="grid gap-5 md:grid-cols-3">
                            <div>
                                <InputLabel
                                    htmlFor="balance_date"
                                    value={t('snapshots.date')}
                                />
                                <TextInput
                                    id="balance_date"
                                    type="date"
                                    className="mt-1 block w-full"
                                    value={data.balance_date}
                                    onChange={(event) =>
                                        setData('balance_date', event.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.balance_date}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="balance"
                                    value={t('snapshots.value')}
                                />
                                <TextInput
                                    id="balance"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    className="mt-1 block w-full"
                                    value={data.balance}
                                    onChange={(event) =>
                                        setData('balance', event.target.value)
                                    }
                                    required
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.balance}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="source_name"
                                    value={t('snapshots.source')}
                                />
                                <TextInput
                                    id="source_name"
                                    className="mt-1 block w-full"
                                    value={data.source_name}
                                    onChange={(event) =>
                                        setData('source_name', event.target.value)
                                    }
                                    placeholder={t('snapshots.sourcePlaceholder')}
                                />
                                <InputError
                                    className="mt-2"
                                    message={errors.source_name}
                                />
                            </div>
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="note"
                                value={t('snapshots.note')}
                            />
                            <textarea
                                id="note"
                                rows={3}
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={data.note}
                                onChange={(event) =>
                                    setData('note', event.target.value)
                                }
                            />
                            <InputError className="mt-2" message={errors.note} />
                        </div>

                        <div className="flex justify-end gap-3">
                            {editingSnapshot ? (
                                <SecondaryButton onClick={stopEditing}>
                                    {t('actions.stopEditing')}
                                </SecondaryButton>
                            ) : null}
                            <PrimaryButton
                                disabled={
                                    processing ||
                                    account.balance_method !== 'snapshot'
                                }
                            >
                                {editingSnapshot
                                    ? t('actions.update')
                                    : t('actions.record')}
                            </PrimaryButton>
                        </div>
                    </form>
                </section>

                <section className="space-y-4">
                    <div>
                        <h2 className="font-semibold text-slate-900">
                            {t('snapshots.historyTitle')}
                        </h2>
                        <p className="mt-1 text-sm text-slate-500">
                            {latestSnapshot
                                ? t('snapshots.latest', {
                                      date: latestSnapshot.balance_date,
                                      balance: formatMoney(
                                          latestSnapshot.balance,
                                          account.currency,
                                      ),
                                      currency: account.currency,
                                  })
                                : t('snapshots.historyEmpty')}
                        </p>
                    </div>

                    {snapshots.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-sm text-slate-500">
                            {t('snapshots.recordFirst')}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-xl border border-slate-200">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th className="px-4 py-3">{t('snapshots.table.date')}</th>
                                        <th className="px-4 py-3">{t('snapshots.table.value')}</th>
                                        <th className="px-4 py-3">{t('snapshots.table.source')}</th>
                                        <th className="px-4 py-3">{t('snapshots.table.note')}</th>
                                        <th className="px-4 py-3">{t('snapshots.table.actions')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white">
                                    {snapshots.map((snapshot) => (
                                        <tr key={snapshot.id}>
                                            <td className="px-4 py-3 text-slate-700">
                                                {snapshot.balance_date}
                                            </td>
                                            <td className="px-4 py-3 font-medium text-slate-900">
                                                {formatMoney(
                                                    snapshot.balance,
                                                    account.currency,
                                                )}{' '}
                                                {account.currency}
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {snapshot.source_name ?? '—'}
                                            </td>
                                            <td className="max-w-sm px-4 py-3 text-slate-600">
                                                {snapshot.note ?? '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <SecondaryButton
                                                        onClick={() =>
                                                            startEditing(snapshot)
                                                        }
                                                    >
                                                        {t('actions.edit')}
                                                    </SecondaryButton>
                                                    <DangerButton
                                                        type="button"
                                                        onClick={() =>
                                                            handleDelete(snapshot)
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
                    )}
                </section>
            </div>
        </AppPage>
    );
}
