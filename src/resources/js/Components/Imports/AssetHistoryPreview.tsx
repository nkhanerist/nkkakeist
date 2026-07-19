import { ImportListItem, ImportPreviewRow } from '@/types/import';
import { formatMoney } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

type AssetHistoryPreviewProps = {
    importRecord: ImportListItem;
    rows: ImportPreviewRow[];
};

export default function AssetHistoryPreview({
    importRecord,
    rows,
}: AssetHistoryPreviewProps) {
    const { t } = useTranslation('imports');

    if (rows.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-600">
                {t('assetHistoryPreview.empty')}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            <div className="rounded-xl border border-violet-200 bg-violet-50 px-5 py-4 text-sm text-violet-950">
                <p className="font-semibold">
                    {t('assetHistoryPreview.initialImportTitle')}
                </p>
                <p className="mt-1 text-violet-800">
                    {t('assetHistoryPreview.initialImportDescription')}
                </p>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200">
                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                        <thead className="bg-slate-50">
                            <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                                <th className="px-4 py-3">
                                    {t('assetHistoryPreview.date')}
                                </th>
                                <th className="px-4 py-3">
                                    {t('assetHistoryPreview.totalAssets')}
                                </th>
                                <th className="px-4 py-3">
                                    {t('assetHistoryPreview.breakdown')}
                                </th>
                                <th className="px-4 py-3">
                                    {t('assetHistoryPreview.status')}
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 bg-white text-slate-700">
                            {rows.map((row) => {
                                const breakdown = (row.raw_payload.breakdown ??
                                    {}) as Record<string, string>;

                                return (
                                    <tr key={row.id}>
                                        <td className="whitespace-nowrap px-4 py-3">
                                            {row.transaction_date}
                                        </td>
                                        <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                            {row.amount
                                                ? formatMoney(row.amount, 'JPY')
                                                : '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex min-w-[24rem] flex-wrap gap-2">
                                                {Object.entries(breakdown).map(
                                                    ([label, value]) => (
                                                        <span
                                                            key={label}
                                                            className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700"
                                                        >
                                                            {label}:{' '}
                                                            {formatMoney(
                                                                value,
                                                                'JPY',
                                                            )}
                                                        </span>
                                                    ),
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {row.is_duplicate_candidate ? (
                                                <span className="text-slate-500">
                                                    {t(
                                                        'assetHistoryPreview.alreadyImported',
                                                    )}
                                                </span>
                                            ) : row.validation_errors.length >
                                              0 ? (
                                                <span className="text-rose-700">
                                                    {row.validation_errors.join(
                                                        ' / ',
                                                    )}
                                                </span>
                                            ) : importRecord.status ===
                                              'imported' ? (
                                                <span className="text-emerald-700">
                                                    {t(
                                                        'assetHistoryPreview.applied',
                                                    )}
                                                </span>
                                            ) : (
                                                <span className="text-emerald-700">
                                                    {t(
                                                        'assetHistoryPreview.ready',
                                                    )}
                                                </span>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
