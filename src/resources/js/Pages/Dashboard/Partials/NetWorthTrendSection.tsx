import LineTrendChart from '@/Components/Charts/LineTrendChart';
import SummaryCard from '@/Components/SummaryCard';
import { DashboardNetWorthTrend } from '@/types/dashboard';
import { formatMoney } from '@/utils/currency';
import { useTranslation } from 'react-i18next';

type NetWorthTrendSectionProps = {
    groups: DashboardNetWorthTrend[];
    periodLabel: string;
};

export default function NetWorthTrendSection({
    groups,
    periodLabel,
}: NetWorthTrendSectionProps) {
    const { t } = useTranslation('dashboard');

    return (
        <section className="space-y-4">
            <div>
                <h2 className="text-lg font-semibold text-slate-900">
                    {t('netWorth.title')}
                </h2>
                <p className="mt-1 text-sm leading-6 text-slate-500">
                    {t('netWorth.description', { period: periodLabel })}
                </p>
            </div>

            {groups.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                    {t('netWorth.emptyAccounts')}
                </div>
            ) : (
                <div className="space-y-6">
                    {groups.map((group) => {
                        const latest = group.points[group.points.length - 1];
                        const series = [
                            {
                                key: 'assets',
                                label: t('netWorth.assets'),
                                currency: group.currency,
                                color: '#059669',
                                points: group.points.map((point) => ({
                                    date: point.date,
                                    value: point.assets,
                                })),
                            },
                            {
                                key: 'liabilities',
                                label: t('netWorth.liabilities'),
                                currency: group.currency,
                                color: '#e11d48',
                                points: group.points.map((point) => ({
                                    date: point.date,
                                    value: point.liabilities,
                                })),
                            },
                            {
                                key: 'net-worth',
                                label: t('netWorth.netWorth'),
                                currency: group.currency,
                                color: '#4f46e5',
                                points: group.points.map((point) => ({
                                    date: point.date,
                                    value: point.net_worth,
                                })),
                            },
                        ];

                        return (
                            <div
                                key={group.currency}
                                className="space-y-4 rounded-2xl border border-slate-200 bg-slate-50/60 p-4"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <h3 className="text-sm font-semibold text-slate-700">
                                        {group.currency}
                                    </h3>
                                    {latest ? (
                                        <p className="text-xs text-slate-500">
                                            {t('netWorth.latest', {
                                                date: latest.date,
                                            })}
                                        </p>
                                    ) : null}
                                </div>

                                {latest ? (
                                    <div className="grid gap-3 md:grid-cols-3">
                                        <SummaryCard
                                            label={t('netWorth.assets')}
                                            value={`${formatMoney(latest.assets, group.currency)} ${group.currency}`}
                                            tone="positive"
                                        />
                                        <SummaryCard
                                            label={t('netWorth.liabilities')}
                                            value={`${formatMoney(latest.liabilities, group.currency)} ${group.currency}`}
                                            tone="negative"
                                        />
                                        <SummaryCard
                                            label={t('netWorth.netWorth')}
                                            value={`${formatMoney(latest.net_worth, group.currency)} ${group.currency}`}
                                            tone={
                                                Number(latest.net_worth) >= 0
                                                    ? 'default'
                                                    : 'negative'
                                            }
                                        />
                                    </div>
                                ) : null}

                                <LineTrendChart
                                    series={series}
                                    currency={group.currency}
                                    emptyMessage={t('netWorth.emptyPeriod')}
                                />
                            </div>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
