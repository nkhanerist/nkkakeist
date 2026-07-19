import LineTrendChart from '@/Components/Charts/LineTrendChart';
import SummaryCard from '@/Components/SummaryCard';
import { DashboardAssetHistoryTrend } from '@/types/dashboard';
import { formatMoney } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

export default function AssetHistoryTrendSection({
    groups,
}: {
    groups: DashboardAssetHistoryTrend[];
}) {
    const { t } = useTranslation('dashboard');

    if (groups.length === 0) {
        return null;
    }

    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">
                    {t('assetHistory.title')}
                </h2>
                <p className="mt-1 text-sm leading-6 text-slate-500">
                    {t('assetHistory.description')}
                </p>
            </div>

            {groups.map((group) => {
                const latest = group.points[group.points.length - 1];
                const breakdownLabels = Array.from(
                    new Set(
                        group.points.flatMap((point) =>
                            Object.keys(point.breakdown),
                        ),
                    ),
                );
                const series = [
                    {
                        key: 'total-assets',
                        label: t('assetHistory.totalAssets'),
                        currency: group.currency,
                        color: '#4f46e5',
                        points: group.points.map((point) => ({
                            date: point.date,
                            value: point.total_assets,
                        })),
                    },
                    ...breakdownLabels.map((label) => ({
                        key: `breakdown-${label}`,
                        label,
                        currency: group.currency,
                        points: group.points
                            .filter(
                                (point) => point.breakdown[label] !== undefined,
                            )
                            .map((point) => ({
                                date: point.date,
                                value: point.breakdown[label],
                            })),
                    })),
                ];

                return (
                    <div
                        key={`${group.source_name}-${group.currency}`}
                        className="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                    >
                        {latest ? (
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                <SummaryCard
                                    label={t('assetHistory.latestTotal', {
                                        date: latest.date,
                                    })}
                                    value={`${formatMoney(latest.total_assets, group.currency)} ${group.currency}`}
                                    tone="positive"
                                />
                                {Object.entries(latest.breakdown).map(
                                    ([label, value]) => (
                                        <SummaryCard
                                            key={label}
                                            label={label}
                                            value={`${formatMoney(value, group.currency)} ${group.currency}`}
                                        />
                                    ),
                                )}
                            </div>
                        ) : null}
                        <LineTrendChart
                            series={series}
                            currency={group.currency}
                            height={320}
                        />
                    </div>
                );
            })}
        </section>
    );
}
