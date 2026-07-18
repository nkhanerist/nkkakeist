import { ImportListItem, ImportPreviewRow } from '@/types/import';
import { formatMoney } from '@/utils/currency';

type AssetHistoryPreviewProps = {
    importRecord: ImportListItem;
    rows: ImportPreviewRow[];
};

export default function AssetHistoryPreview({ importRecord, rows }: AssetHistoryPreviewProps) {
    if (rows.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-600">
                解析済みの資産推移がありません。
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border border-slate-200">
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-slate-200 text-sm">
                    <thead className="bg-slate-50">
                        <tr className="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <th className="px-4 py-3">日付</th>
                            <th className="px-4 py-3">総資産</th>
                            <th className="px-4 py-3">資産内訳</th>
                            <th className="px-4 py-3">状態</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-200 bg-white text-slate-700">
                        {rows.map((row) => {
                            const breakdown = (row.raw_payload.breakdown ?? {}) as Record<string, string>;

                            return (
                                <tr key={row.id}>
                                    <td className="whitespace-nowrap px-4 py-3">{row.transaction_date}</td>
                                    <td className="whitespace-nowrap px-4 py-3 font-medium text-slate-900">
                                        {row.amount ? formatMoney(row.amount, 'JPY') : '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex min-w-[24rem] flex-wrap gap-2">
                                            {Object.entries(breakdown).map(([label, value]) => (
                                                <span key={label} className="rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                                                    {label}: {formatMoney(value, 'JPY')}
                                                </span>
                                            ))}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {row.is_duplicate_candidate ? (
                                            <span className="text-amber-700">取込済み</span>
                                        ) : row.validation_errors.length > 0 ? (
                                            <span className="text-rose-700">{row.validation_errors.join(' / ')}</span>
                                        ) : importRecord.status === 'imported' ? (
                                            <span className="text-emerald-700">反映済み</span>
                                        ) : (
                                            <span className="text-emerald-700">反映対象</span>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
