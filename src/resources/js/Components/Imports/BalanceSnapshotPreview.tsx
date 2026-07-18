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

type InvestmentPosition = {
    instrument_name: string;
    quantity: string | null;
    average_acquisition_price: string | null;
    unit_price: string | null;
    valuation: string;
    unrealized_gain: string | null;
    currency: string;
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

function rawPositions(row: ImportPreviewRow, fallbackCurrency: string): InvestmentPosition[] {
    const value = row.raw_payload.positions;

    if (! Array.isArray(value)) {
        return [];
    }

    return value.flatMap((position) => {
        if (typeof position !== 'object' || position === null) {
            return [];
        }

        const candidate = position as Record<string, unknown>;

        if (
            typeof candidate.instrument_name !== 'string' ||
            typeof candidate.valuation !== 'string'
        ) {
            return [];
        }

        const optionalString = (key: string) => {
            const value = candidate[key];

            return typeof value === 'string' ? value : null;
        };

        return [{
            instrument_name: candidate.instrument_name,
            quantity: optionalString('quantity'),
            average_acquisition_price: optionalString('average_acquisition_price'),
            unit_price: optionalString('unit_price'),
            valuation: candidate.valuation,
            unrealized_gain: optionalString('unrealized_gain'),
            currency: optionalString('currency') ?? fallbackCurrency,
        }];
    });
}

function formatQuantity(value: string): string {
    return new Intl.NumberFormat('ja-JP', {
        maximumFractionDigits: 8,
    }).format(Number(value));
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
    const buildRememberMappings = () =>
        Object.fromEntries(
            rows.map((row) => [
                row.id,
                row.account_name === 'Money Forward 年金' && row.resolved_account === null,
            ]),
        );
    const [rememberMappings, setRememberMappings] = useState<Record<number, boolean>>(
        buildRememberMappings,
    );

    useEffect(() => {
        setSelections(buildSelections());
        setRememberMappings(buildRememberMappings());
    }, [rows]);

    const rowError = (rowId: number) => {
        const error = page.props.errors?.[`resolved_account_id.${rowId}`];

        return Array.isArray(error) ? (error[0] ?? null) : (error ?? null);
    };

    const updateAccount = (rowId: number) => {
        router.put(
            route('imports.rows.update-account', [importRecord.id, rowId]),
            {
                resolved_account_id: selections[rowId] || null,
                remember_mapping: rememberMappings[rowId] ?? false,
            },
            { preserveScroll: true },
        );
    };

    const updateReplacement = (rowId: number, replaceExisting: boolean) => {
        router.put(
            route('imports.rows.update-replacement', [importRecord.id, rowId]),
            { replace_existing: replaceExisting },
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
                const positions = rawPositions(row, currency);
                const positionValuationTotal = positions.reduce(
                    (total, position) => total + Number(position.valuation),
                    0,
                );
                const sameDaySnapshot = row.same_day_snapshot;
                const hasDifferentSameDayBalance = sameDaySnapshot !== null
                    && row.amount !== null
                    && Number(sameDaySnapshot.balance) !== Number(row.amount)
                    && ! row.is_duplicate_candidate;
                const replacementSelected = sameDaySnapshot !== null
                    && row.replace_account_snapshot_id === sameDaySnapshot.id;
                const replacementError = page.props.errors?.[
                    `replace_existing.${row.id}`
                ];

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

                        {hasDifferentSameDayBalance && sameDaySnapshot ? (
                            <div
                                className={`mt-4 rounded-lg border px-4 py-3 ${
                                    replacementSelected
                                        ? 'border-emerald-200 bg-emerald-50'
                                        : 'border-amber-200 bg-amber-50'
                                }`}
                            >
                                <div className="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <p className={`text-sm font-semibold ${
                                            replacementSelected
                                                ? 'text-emerald-900'
                                                : 'text-amber-900'
                                        }`}>
                                            同日残高の置き換え
                                        </p>
                                        <p className={`mt-1 text-sm ${
                                            replacementSelected
                                                ? 'text-emerald-800'
                                                : 'text-amber-800'
                                        }`}>
                                            既存値{' '}
                                            {formatMoney(
                                                sameDaySnapshot.balance,
                                                currency,
                                            )}{' '}
                                            → 新しい値{' '}
                                            {formatMoney(row.amount ?? '0', currency)}{' '}
                                            {currency}
                                        </p>
                                        <p className="mt-1 text-xs text-slate-600">
                                            {sameDaySnapshot.balance_date}
                                            {sameDaySnapshot.source_name
                                                ? ` / ${sameDaySnapshot.source_name}`
                                                : ''}
                                            {sameDaySnapshot.import_id
                                                ? ` / import ${sameDaySnapshot.import_id}`
                                                : ''}
                                        </p>
                                    </div>
                                    {importRecord.status !== 'imported' ? (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                updateReplacement(
                                                    row.id,
                                                    ! replacementSelected,
                                                )
                                            }
                                            className={`rounded-md border px-3 py-2 text-sm font-semibold shadow-sm ${
                                                replacementSelected
                                                    ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50'
                                                    : 'border-amber-300 bg-white text-amber-800 hover:bg-amber-100'
                                            }`}
                                        >
                                            {replacementSelected
                                                ? '置き換えを取り消す'
                                                : 'この値で置き換える'}
                                        </button>
                                    ) : null}
                                </div>
                                <p className={`mt-2 text-xs ${
                                    replacementSelected
                                        ? 'text-emerald-700'
                                        : 'text-amber-700'
                                }`}>
                                    {replacementSelected
                                        ? '確定時に既存の同日残高を削除し、この残高と銘柄明細へ置き換えます。'
                                        : 'まだ置き換えは実行されません。新旧の金額を確認して選択してください。'}
                                </p>
                                {replacementError ? (
                                    <p className="mt-2 text-xs text-rose-700">
                                        {Array.isArray(replacementError)
                                            ? replacementError[0]
                                            : replacementError}
                                    </p>
                                ) : null}
                            </div>
                        ) : null}

                        {positions.length > 0 ? (
                            <details className="mt-4 overflow-hidden rounded-lg border border-slate-200 bg-slate-50">
                                <summary className="cursor-pointer px-4 py-3 text-sm font-medium text-slate-800">
                                    銘柄別内訳 {positions.length}件 / 評価額計{' '}
                                    {formatMoney(positionValuationTotal, currency)} {currency}
                                </summary>
                                <div className="overflow-x-auto border-t border-slate-200 bg-white">
                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                        <thead className="bg-slate-50 text-xs text-slate-500">
                                            <tr>
                                                <th className="px-4 py-2 text-left font-medium">銘柄</th>
                                                <th className="px-4 py-2 text-right font-medium">保有数</th>
                                                <th className="px-4 py-2 text-right font-medium">平均取得単価</th>
                                                <th className="px-4 py-2 text-right font-medium">現在値</th>
                                                <th className="px-4 py-2 text-right font-medium">評価額</th>
                                                <th className="px-4 py-2 text-right font-medium">評価損益</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100 text-slate-700">
                                            {positions.map((position, index) => (
                                                <tr key={`${position.instrument_name}-${index}`}>
                                                    <td className="whitespace-nowrap px-4 py-2 font-medium text-slate-900">
                                                        {position.instrument_name}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right">
                                                        {position.quantity
                                                            ? formatQuantity(position.quantity)
                                                            : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right">
                                                        {position.average_acquisition_price
                                                            ? formatMoney(
                                                                  position.average_acquisition_price,
                                                                  position.currency,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right">
                                                        {position.unit_price
                                                            ? formatMoney(
                                                                  position.unit_price,
                                                                  position.currency,
                                                              )
                                                            : '—'}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right font-medium text-slate-900">
                                                        {formatMoney(
                                                            position.valuation,
                                                            position.currency,
                                                        )}
                                                    </td>
                                                    <td className="whitespace-nowrap px-4 py-2 text-right">
                                                        {position.unrealized_gain
                                                            ? formatMoney(
                                                                  position.unrealized_gain,
                                                                  position.currency,
                                                              )
                                                            : '—'}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </details>
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
                                <label className="mt-2 flex items-start gap-2 text-xs text-slate-600">
                                    <input
                                        type="checkbox"
                                        checked={rememberMappings[row.id] ?? false}
                                        onChange={(event) =>
                                            setRememberMappings((current) => ({
                                                ...current,
                                                [row.id]: event.target.checked,
                                            }))
                                        }
                                        disabled={
                                            importRecord.status === 'imported'
                                            || ! selections[row.id]
                                        }
                                        className="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50"
                                    />
                                    <span>
                                        この取得名を口座の取込用別名に追加し、今後も自動対応する
                                    </span>
                                </label>
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
                                    ? '同じ残高はスキップし、未保存の銘柄内訳がある場合だけ追記します。'
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
