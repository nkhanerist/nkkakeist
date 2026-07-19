import AppPage from '@/Components/AppPage';
import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import BalanceSnapshotPreview from '@/Components/Imports/BalanceSnapshotPreview';
import AssetHistoryPreview from '@/Components/Imports/AssetHistoryPreview';
import { PageProps } from '@/types';
import {
    ImportAccountOption,
    ImportListItem,
    ImportPreviewRow,
    JrePointReconciliation,
} from '@/types/import';
import { formatMoney } from '@/utils/currency';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

type ShowProps = {
    import: ImportListItem;
    accountOptions: ImportAccountOption[];
    rows: ImportPreviewRow[];
    jrePointReconciliation: JrePointReconciliation | null;
};

export default function Show({
    import: importRecord,
    accountOptions,
    rows,
    jrePointReconciliation,
}: ShowProps) {
    const { t, i18n } = useTranslation('imports');
    const page = usePage<PageProps>();
    const flashError = page.props.flash.error;
    const buildTransferAccountSelections = (previewRows: ImportPreviewRow[]) =>
        Object.fromEntries(
            previewRows.map((row) => [
                row.id,
                row.manual_resolved_transfer_account_id
                    ? String(row.manual_resolved_transfer_account_id)
                    : '',
            ]),
        );
    const [transferAccountSelections, setTransferAccountSelections] = useState<
        Record<number, string>
    >(() => buildTransferAccountSelections(rows));

    useEffect(() => {
        setTransferAccountSelections(buildTransferAccountSelections(rows));
    }, [rows]);

    const transferRowsExist = rows.some(
        (row) => row.detected_type === 'transfer',
    );
    const isBalanceSnapshot = importRecord.source_name === 'balance_snapshot';
    const isAssetHistory = importRecord.source_name === 'asset_history';
    const formatPoints = (value: string) =>
        t('preview.jrePoint.points', {
            value: Number(value).toLocaleString(
                i18n.resolvedLanguage === 'en' ? 'en-US' : 'ja-JP',
            ),
        });

    const transferAccountErrorMessage = (rowId: number) => {
        const error =
            page.props.errors?.[`resolved_transfer_account_id.${rowId}`];

        if (Array.isArray(error)) {
            return error[0] ?? null;
        }

        return error ?? null;
    };

    const handleCommit = () => {
        router.post(route('imports.commit', importRecord.id));
    };

    const handleReparse = () => {
        router.post(route('imports.parse', importRecord.id));
    };

    const handleDelete = () => {
        const message =
            importRecord.status === 'imported'
                ? t('preview.deleteImportedConfirm', {
                      filename: importRecord.original_filename,
                  })
                : t('preview.deleteConfirm', {
                      filename: importRecord.original_filename,
                  });

        if (!window.confirm(message)) {
            return;
        }

        router.delete(route('imports.destroy', importRecord.id));
    };

    const handleTransferAccountSelectionChange = (
        rowId: number,
        value: string,
    ) => {
        setTransferAccountSelections((current) => ({
            ...current,
            [rowId]: value,
        }));
    };

    const handleTransferAccountUpdate = (rowId: number) => {
        router.put(
            route('imports.rows.update-transfer-account', [
                importRecord.id,
                rowId,
            ]),
            {
                resolved_transfer_account_id:
                    transferAccountSelections[rowId] || null,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const handleTransferAccountClear = (rowId: number) => {
        setTransferAccountSelections((current) => ({
            ...current,
            [rowId]: '',
        }));

        router.put(
            route('imports.rows.update-transfer-account', [
                importRecord.id,
                rowId,
            ]),
            {
                resolved_transfer_account_id: null,
            },
            {
                preserveScroll: true,
            },
        );
    };

    return (
        <AppPage
            title={
                isBalanceSnapshot
                    ? t('preview.title.balanceSnapshot')
                    : isAssetHistory
                      ? t('preview.title.assetHistory')
                      : t('preview.title.default')
            }
            description={
                isBalanceSnapshot
                    ? t('preview.description.balanceSnapshot')
                    : isAssetHistory
                      ? t('preview.description.assetHistory')
                      : t('preview.description.default')
            }
        >
            <div className="space-y-6">
                <div className="grid gap-4 rounded-xl border border-slate-200 bg-white p-5 md:grid-cols-5">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('preview.summary.filename')}
                        </p>
                        <p className="mt-2 text-sm font-medium text-slate-900">
                            {importRecord.original_filename}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('preview.summary.source')}
                        </p>
                        <p className="mt-2 text-sm font-medium text-slate-900">
                            {importRecord.source_label}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('preview.summary.status')}
                        </p>
                        <p className="mt-2 text-sm font-medium text-slate-900">
                            {importRecord.status_label}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('preview.summary.totalRows')}
                        </p>
                        <p className="mt-2 text-sm font-medium text-slate-900">
                            {importRecord.total_rows}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-wider text-slate-500">
                            {t('preview.summary.destination')}
                        </p>
                        <p className="mt-2 text-sm font-medium text-slate-900">
                            {isBalanceSnapshot
                                ? t('preview.summary.perItem')
                                : isAssetHistory
                                  ? t('preview.summary.noAllocation')
                                  : importRecord.account
                                    ? t('preview.summary.account', {
                                          name: importRecord.account.name,
                                          currency:
                                              importRecord.account.currency,
                                      })
                                    : t('preview.summary.none')}
                        </p>
                    </div>
                </div>

                {importRecord.error_message ? (
                    <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {importRecord.error_message}
                    </div>
                ) : null}

                {flashError ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {flashError}
                    </div>
                ) : null}

                {jrePointReconciliation ? (
                    <div className="space-y-4 rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="font-semibold text-emerald-950">
                                    {t('preview.jrePoint.title')}
                                </h2>
                                <p className="mt-1 text-xs text-emerald-800">
                                    {t('preview.jrePoint.capturedAt', {
                                        date: jrePointReconciliation.captured_at,
                                    })}
                                    {jrePointReconciliation.nearest_expiry
                                        ? ` / ${t(
                                              'preview.jrePoint.nearestExpiry',
                                              {
                                                  date: jrePointReconciliation.nearest_expiry,
                                              },
                                          )}`
                                        : ''}
                                </p>
                            </div>
                            <span
                                className={`rounded-full px-3 py-1 text-xs font-semibold ${
                                    Number(
                                        jrePointReconciliation.difference,
                                    ) === 0
                                        ? 'bg-emerald-100 text-emerald-800'
                                        : 'bg-amber-100 text-amber-800'
                                }`}
                            >
                                {Number(jrePointReconciliation.difference) === 0
                                    ? t('preview.jrePoint.matched')
                                    : t('preview.jrePoint.difference', {
                                          value: formatPoints(
                                              jrePointReconciliation.difference,
                                          ),
                                      })}
                            </span>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {[
                                [
                                    t('preview.jrePoint.officialTotal'),
                                    jrePointReconciliation.official_total,
                                ],
                                [
                                    t('preview.jrePoint.regular'),
                                    jrePointReconciliation.official_regular,
                                ],
                                [
                                    t('preview.jrePoint.limited'),
                                    jrePointReconciliation.official_limited,
                                ],
                                [
                                    t('preview.jrePoint.ledgerBefore'),
                                    jrePointReconciliation.ledger_balance_before_import,
                                ],
                                [
                                    t('preview.jrePoint.expectedAfter'),
                                    jrePointReconciliation.expected_balance_after_import,
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="rounded-lg bg-white px-4 py-3"
                                >
                                    <p className="text-xs font-medium text-slate-500">
                                        {label}
                                    </p>
                                    <p className="mt-1 font-semibold text-slate-900">
                                        {formatPoints(value)}
                                    </p>
                                </div>
                            ))}
                        </div>
                        {importRecord.status !== 'imported' &&
                        jrePointReconciliation.is_initial_import &&
                        jrePointReconciliation.recommended_initial_balance !==
                            null ? (
                            <p className="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                                {t('preview.jrePoint.initialAdjustment', {
                                    value: formatPoints(
                                        jrePointReconciliation.recommended_initial_balance,
                                    ),
                                })}
                            </p>
                        ) : null}
                    </div>
                ) : null}

                {transferRowsExist ? (
                    <div className="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
                        {t('preview.transferHelp')}
                    </div>
                ) : null}

                <div className="flex flex-wrap items-center gap-3">
                    {importRecord.status !== 'imported' ? (
                        <>
                            <PrimaryButton type="button" onClick={handleCommit}>
                                {isBalanceSnapshot
                                    ? t('preview.actions.applyBalance')
                                    : isAssetHistory
                                      ? t('preview.actions.applyAssetHistory')
                                      : t('preview.actions.applyImport')}
                            </PrimaryButton>

                            <PrimaryButton
                                type="button"
                                onClick={handleReparse}
                                className="border border-slate-300 bg-slate-700 text-white hover:bg-slate-600 focus:bg-slate-600 active:bg-slate-800"
                            >
                                {t('preview.actions.reparse')}
                            </PrimaryButton>
                        </>
                    ) : null}

                    <DangerButton type="button" onClick={handleDelete}>
                        {t('preview.actions.delete')}
                    </DangerButton>

                    <Link
                        href={route('imports.index')}
                        className="text-sm font-medium text-slate-600 hover:text-slate-900"
                    >
                        {t('preview.actions.back')}
                    </Link>
                </div>

                {isBalanceSnapshot ? (
                    <BalanceSnapshotPreview
                        importRecord={importRecord}
                        accountOptions={accountOptions}
                        rows={rows}
                    />
                ) : isAssetHistory ? (
                    <AssetHistoryPreview
                        importRecord={importRecord}
                        rows={rows}
                    />
                ) : rows.length === 0 ? (
                    <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                        <p className="text-sm text-slate-600">
                            {t('preview.empty')}
                        </p>
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-xl border border-slate-200">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                        <th className="px-4 py-3">
                                            {t('preview.table.row')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.date')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.type')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.calculation')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.balance')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.amount')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.account')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.counterparty')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.category')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.subcategory')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.merchant')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.description')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.status')}
                                        </th>
                                        <th className="px-4 py-3">
                                            {t('preview.table.issues')}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-200 bg-white text-sm text-slate-700">
                                    {rows.map((row) => (
                                        <tr key={row.id}>
                                            <td className="px-4 py-4">
                                                {row.row_number}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.transaction_date ?? '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.detected_type
                                                    ? t(
                                                          `preview.transactionTypes.${row.detected_type}`,
                                                          {
                                                              defaultValue:
                                                                  row.detected_type,
                                                          },
                                                      )
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.is_calculation_target ===
                                                null
                                                    ? '-'
                                                    : row.is_calculation_target
                                                      ? t(
                                                            'preview.flags.included',
                                                        )
                                                      : t(
                                                            'preview.flags.excluded',
                                                        )}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.affects_account_balance ===
                                                null
                                                    ? '-'
                                                    : row.affects_account_balance
                                                      ? t(
                                                            'preview.flags.applied',
                                                        )
                                                      : t(
                                                            'preview.flags.excluded',
                                                        )}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.amount
                                                    ? formatMoney(
                                                          row.amount,
                                                          row.resolved_account
                                                              ?.currency ??
                                                              importRecord
                                                                  .account
                                                                  ?.currency ??
                                                              'JPY',
                                                      )
                                                    : '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="space-y-1">
                                                    <p>
                                                        {row.account_name ??
                                                            '-'}
                                                    </p>
                                                    {row.resolved_account ? (
                                                        <p className="text-xs text-slate-500">
                                                            →{' '}
                                                            {
                                                                row
                                                                    .resolved_account
                                                                    .name
                                                            }
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.detected_type ===
                                                'transfer' ? (
                                                    <div className="space-y-2">
                                                        <p>-</p>
                                                        {row.resolved_transfer_account ? (
                                                            <p className="text-xs text-slate-500">
                                                                →{' '}
                                                                {
                                                                    row
                                                                        .resolved_transfer_account
                                                                        .name
                                                                }
                                                            </p>
                                                        ) : null}
                                                        {importRecord.status !==
                                                        'imported' ? (
                                                            <div className="space-y-2">
                                                                <select
                                                                    value={
                                                                        transferAccountSelections[
                                                                            row
                                                                                .id
                                                                        ] ?? ''
                                                                    }
                                                                    onChange={(
                                                                        event,
                                                                    ) =>
                                                                        handleTransferAccountSelectionChange(
                                                                            row.id,
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    className="w-full rounded-md border border-slate-300 px-2 py-1 text-xs text-slate-700"
                                                                >
                                                                    <option value="">
                                                                        {t(
                                                                            'preview.selectCounterparty',
                                                                        )}
                                                                    </option>
                                                                    {accountOptions
                                                                        .filter(
                                                                            (
                                                                                accountOption,
                                                                            ) =>
                                                                                accountOption.id !==
                                                                                    row
                                                                                        .resolved_account
                                                                                        ?.id ||
                                                                                accountOption.id ===
                                                                                    row.manual_resolved_transfer_account_id,
                                                                        )
                                                                        .map(
                                                                            (
                                                                                accountOption,
                                                                            ) => (
                                                                                <option
                                                                                    key={
                                                                                        accountOption.id
                                                                                    }
                                                                                    value={
                                                                                        accountOption.id
                                                                                    }
                                                                                >
                                                                                    {
                                                                                        accountOption.name
                                                                                    }{' '}
                                                                                    (
                                                                                    {
                                                                                        accountOption.currency
                                                                                    }

                                                                                    )
                                                                                </option>
                                                                            ),
                                                                        )}
                                                                </select>
                                                                <PrimaryButton
                                                                    type="button"
                                                                    className="px-3 py-1 text-xs"
                                                                    onClick={() =>
                                                                        handleTransferAccountUpdate(
                                                                            row.id,
                                                                        )
                                                                    }
                                                                >
                                                                    {row.manual_resolved_transfer_account_id
                                                                        ? t(
                                                                              'preview.actions.update',
                                                                          )
                                                                        : t(
                                                                              'preview.actions.apply',
                                                                          )}
                                                                </PrimaryButton>
                                                                {row.manual_resolved_transfer_account_id !==
                                                                null ? (
                                                                    <button
                                                                        type="button"
                                                                        className="text-xs font-medium text-slate-600 hover:text-slate-900"
                                                                        onClick={() =>
                                                                            handleTransferAccountClear(
                                                                                row.id,
                                                                            )
                                                                        }
                                                                    >
                                                                        {t(
                                                                            'preview.actions.clearManual',
                                                                        )}
                                                                    </button>
                                                                ) : null}
                                                                {transferAccountErrorMessage(
                                                                    row.id,
                                                                ) ? (
                                                                    <p className="text-xs text-rose-700">
                                                                        {transferAccountErrorMessage(
                                                                            row.id,
                                                                        )}
                                                                    </p>
                                                                ) : null}
                                                            </div>
                                                        ) : null}
                                                    </div>
                                                ) : (
                                                    '-'
                                                )}
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="space-y-1">
                                                    <p>
                                                        {row.category_name ??
                                                            '-'}
                                                    </p>
                                                    {row.resolved_category ? (
                                                        <p className="text-xs text-slate-500">
                                                            →{' '}
                                                            {
                                                                row
                                                                    .resolved_category
                                                                    .name
                                                            }{' '}
                                                            {row.category_resolution_source ===
                                                            'rule'
                                                                ? '(rule)'
                                                                : '(csv)'}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="space-y-1">
                                                    <p>
                                                        {row.subcategory_name ??
                                                            '-'}
                                                    </p>
                                                    {row.resolved_subcategory ? (
                                                        <p className="text-xs text-slate-500">
                                                            →{' '}
                                                            {
                                                                row
                                                                    .resolved_subcategory
                                                                    .name
                                                            }{' '}
                                                            {row.subcategory_resolution_source ===
                                                            'rule'
                                                                ? '(rule)'
                                                                : '(csv)'}
                                                        </p>
                                                    ) : null}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.merchant_name ?? '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                {row.description ?? '-'}
                                            </td>
                                            <td className="px-4 py-4">
                                                <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700">
                                                    {t(
                                                        `preview.rowStatuses.${row.status}`,
                                                        {
                                                            defaultValue:
                                                                row.status,
                                                        },
                                                    )}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4">
                                                <div className="space-y-2">
                                                    {row.is_duplicate_candidate ? (
                                                        <p className="text-xs font-semibold text-amber-700">
                                                            {t(
                                                                'preview.rowMessages.duplicateCandidate',
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.detected_type ===
                                                        'transfer' &&
                                                    row.resolved_transfer_account ? (
                                                        <p className="text-xs text-sky-700">
                                                            {t(
                                                                'preview.rowMessages.transferImport',
                                                                {
                                                                    source:
                                                                        row
                                                                            .resolved_account
                                                                            ?.name ??
                                                                        '-',
                                                                    destination:
                                                                        row
                                                                            .resolved_transfer_account
                                                                            .name,
                                                                },
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.detected_type ===
                                                        'transfer' &&
                                                    row.transfer_resolution
                                                        .source_resolution_message ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.sourceAccount',
                                                                {
                                                                    message:
                                                                        row
                                                                            .transfer_resolution
                                                                            .source_resolution_message,
                                                                },
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.detected_type ===
                                                        'transfer' &&
                                                    row.transfer_resolution
                                                        .destination_resolution_message ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.counterpartyCandidate',
                                                                {
                                                                    message:
                                                                        row
                                                                            .transfer_resolution
                                                                            .destination_resolution_message,
                                                                },
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.detected_type ===
                                                        'transfer' &&
                                                    row.transfer_resolution
                                                        .unresolved_reason ? (
                                                        <p className="text-xs text-rose-700">
                                                            {
                                                                row
                                                                    .transfer_resolution
                                                                    .unresolved_reason
                                                            }
                                                        </p>
                                                    ) : null}
                                                    {row.matched_classification_rule ? (
                                                        <div className="space-y-1 text-xs text-indigo-700">
                                                            <p>
                                                                {t(
                                                                    'preview.rowMessages.ruleMatched',
                                                                    {
                                                                        name: row
                                                                            .matched_classification_rule
                                                                            .name,
                                                                    },
                                                                )}
                                                            </p>
                                                            {row
                                                                .rule_applied_fields
                                                                .length > 0 ? (
                                                                <p>
                                                                    {t(
                                                                        'preview.rowMessages.fieldsFilled',
                                                                        {
                                                                            fields: row.rule_applied_fields.join(
                                                                                ', ',
                                                                            ),
                                                                        },
                                                                    )}
                                                                </p>
                                                            ) : null}
                                                        </div>
                                                    ) : null}
                                                    {row.category_resolution_source ===
                                                    'csv' ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.categoryCsv',
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.category_resolution_source ===
                                                    'rule' ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.categoryRule',
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.subcategory_resolution_source ===
                                                    'rule' ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.subcategoryRule',
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.calculation_target_source ? (
                                                        <p className="text-xs text-slate-500">
                                                            {t(
                                                                'preview.rowMessages.calculationFlag',
                                                                {
                                                                    source:
                                                                        row.calculation_target_source ===
                                                                        'rule'
                                                                            ? t(
                                                                                  'preview.rowMessages.ruleSource',
                                                                              )
                                                                            : t(
                                                                                  'preview.rowMessages.fileSource',
                                                                              ),
                                                                },
                                                            )}
                                                        </p>
                                                    ) : null}
                                                    {row.validation_errors
                                                        .length > 0 ? (
                                                        <ul className="space-y-1 text-xs text-rose-700">
                                                            {row.validation_errors.map(
                                                                (error) => (
                                                                    <li
                                                                        key={
                                                                            error
                                                                        }
                                                                    >
                                                                        {error}
                                                                    </li>
                                                                ),
                                                            )}
                                                        </ul>
                                                    ) : null}
                                                    {row.validation_errors
                                                        .length === 0 &&
                                                    !row.is_duplicate_candidate ? (
                                                        <span className="text-xs text-slate-500">
                                                            -
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </AppPage>
    );
}
