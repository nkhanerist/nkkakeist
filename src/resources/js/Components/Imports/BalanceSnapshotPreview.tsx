import PrimaryButton from '@/Components/PrimaryButton';
import { PageProps } from '@/types';
import { ImportAccountOption, ImportListItem, ImportPreviewRow } from '@/types/import';
import { formatMoney } from '@/utils/currency';
import { router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type BalanceSnapshotPreviewProps = {
    importRecord: ImportListItem;
    accountOptions: ImportAccountOption[];
    rows: ImportPreviewRow[];
};

const kindLabels: Record<string, string> = {
    valuation: '時価評価額',
    account_balance: '公式口座残高',
    card_outstanding: 'カード利用残高',
};

function rawString(row: ImportPreviewRow, key: string): string | null {
    const value = row.raw_payload[key];

    return typeof value === 'string' && value !== '' ? value : null;
}

export default function BalanceSnapshotPreview({
    importRecord,
    accountOptions,
    rows,
}: BalanceSnapshotPreviewProps) {
    const page = usePage<PageProps>();
    const buildSelections = () =>
        Object.fromEntries(
            rows.map((row) => [
                row.id,
                String(
                    row.manual_resolved_account_id ??
                        row.resolved_account?.id ??
                        '',
                ),
            ]),
        );
    const [selections, setSelections] = useState<Record<number, string>>(
        buildSelections,
    );

    useEffect(() => {
        setSelections(buildSelections());
    }, [rows]);

    const rowError = (rowId: number) => {
        const error = page.props.errors?.[`resolved_account_id.${rowId}`];

        return Array.isArray(error) ? (error[0] ?? null) : (error ?? null);
    };

    const updateAccount = (rowId: number) => {
        router.put(
            route('imports.rows.update-account', [importRecord.id, rowId]),
            { resolved_account_id: selections[rowId] || null },
            { preserveScroll: true },
        );
    };

    const availableAccounts = (row: ImportPreviewRow) => {
        const kind = rawString(row, 'balance_kind');
        const currency = rawString(row, 'currency');

        return accountOptions.filter((account) => {
            if (! account.is_active || (currency && account.currency !== currency)) {
                return false;
            }

            if (kind === 'valuation') {
                return account.balance_method === 'snapshot';
            }

            if (kind === 'card_outstanding') {
                return account.balance_role === 'liability';
            }

            return (
                account.balance_method === 'ledger' &&
                account.balance_role !== 'clearing'
            );
        });
    };

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                取得元の口座名、残高種別、更新日時を確認してください。カード利用残高はアプリ内では負債として負数保存します。
            </div>

            {rows.map((row) => {
                const kind = rawString(row, 'balance_kind') ?? 'unknown';
                const currency = rawString(row, 'currency') ?? 'JPY';
                const nextPaymentAmount = rawString(row, 'next_payment_amount');
                const nextPaymentDate = rawString(row, 'next_payment_date');
                const sourceUpdatedAt = rawString(row, 'source_updated_at');

                return (
                    <div
                        key={row.id}
                        className="rounded-xl border border-slate-200 bg-white p-5"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h3 className="font-semibold text-slate-900">
                                        {row.account_name ?? '口座名なし'}
                                    </h3>
                                    <span className="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                        {kindLabels[kind] ?? kind}
                                    </span>
                                    {row.is_duplicate_candidate ? (
                                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700">
                                            取込済み
                                        </span>
                                    ) : null}
                                </div>
                                <p className="mt-1 text-xs text-slate-500">
                                    残高日 {row.transaction_date ?? '—'}
                                    {sourceUpdatedAt
                                        ? ` / 取得元更新 ${sourceUpdatedAt}`
                                        : ''}
                                </p>
                            </div>
                            <div className="text-right">
                                <p className="text-xs text-slate-500">保存する残高</p>
                                <p className="text-xl font-semibold text-slate-900">
                                    {row.amount
                                        ? formatMoney(row.amount, currency)
                                        : '—'}{' '}
                                    <span className="text-xs font-normal text-slate-500">
                                        {currency}
                                    </span>
                                </p>
                            </div>
                        </div>

                        {nextPaymentAmount || nextPaymentDate ? (
                            <div className="mt-4 rounded-lg bg-slate-50 px-4 py-3 text-sm text-slate-700">
                                次回引落し:{' '}
                                {nextPaymentAmount
                                    ? `${formatMoney(nextPaymentAmount, currency)} ${currency}`
                                    : '金額未取得'}
                                {nextPaymentDate ? ` / ${nextPaymentDate}` : ''}
                            </div>
                        ) : null}

                        <div className="mt-4 grid gap-4 md:grid-cols-[1fr_auto] md:items-end">
                            <div>
                                <label
                                    htmlFor={`resolved-account-${row.id}`}
                                    className="block text-sm font-medium text-slate-700"
                                >
                                    アプリ内の取込先口座
                                </label>
                                <select
                                    id={`resolved-account-${row.id}`}
                                    value={selections[row.id] ?? ''}
                                    onChange={(event) =>
                                        setSelections((current) => ({
                                            ...current,
                                            [row.id]: event.target.value,
                                        }))
                                    }
                                    disabled={importRecord.status === 'imported'}
                                    className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100"
                                >
                                    <option value="">口座を選択してください</option>
                                    {availableAccounts(row).map((account) => (
                                        <option key={account.id} value={account.id}>
                                            {account.name} ({account.currency})
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {importRecord.status !== 'imported' ? (
                                <PrimaryButton
                                    type="button"
                                    onClick={() => updateAccount(row.id)}
                                >
                                    口座対応を保存
                                </PrimaryButton>
                            ) : null}
                        </div>

                        {row.resolved_account ? (
                            <p className="mt-2 text-xs text-emerald-700">
                                取込先: {row.resolved_account.name}
                            </p>
                        ) : null}
                        {rowError(row.id) ? (
                            <p className="mt-2 text-xs text-rose-700">
                                {rowError(row.id)}
                            </p>
                        ) : null}
                        {row.validation_errors.length > 0 ? (
                            <ul className="mt-3 list-disc space-y-1 pl-5 text-xs text-rose-700">
                                {row.validation_errors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        ) : (
                            <p className="mt-3 text-xs text-slate-500">
                                {row.is_duplicate_candidate
                                    ? '同じ残高は確定時に安全にスキップします。'
                                    : row.status === 'imported'
                                      ? '残高へ反映済みです。'
                                      : '反映準備ができています。'}
                            </p>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
