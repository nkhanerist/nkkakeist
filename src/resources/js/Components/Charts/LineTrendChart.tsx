import { TrendSeries } from '@/types/chart';
import { Link } from '@inertiajs/react';

type LineTrendChartProps = {
    series: TrendSeries[];
    currency: string;
    emptyMessage?: string;
    height?: number;
};

const colors = [
    '#4f46e5',
    '#059669',
    '#e11d48',
    '#d97706',
    '#0284c7',
    '#7c3aed',
    '#0f766e',
    '#c2410c',
];

function compactAmount(value: number, currency: string): string {
    return new Intl.NumberFormat('ja-JP', {
        notation: 'compact',
        maximumFractionDigits: 1,
        style: 'decimal',
    }).format(value) + ` ${currency}`;
}

function dateLabel(date: string): string {
    const [, month, day] = date.split('-');

    return `${Number(month)}/${Number(day)}`;
}

export default function LineTrendChart({
    series,
    currency,
    emptyMessage = '表示できる推移データがありません。',
    height = 280,
}: LineTrendChartProps) {
    const visibleSeries = series.filter((item) => item.points.length > 0);
    const dates = Array.from(
        new Set(visibleSeries.flatMap((item) => item.points.map((point) => point.date))),
    ).sort();

    if (visibleSeries.length === 0 || dates.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                {emptyMessage}
            </div>
        );
    }

    const width = 920;
    const padding = { top: 20, right: 24, bottom: 38, left: 82 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const values = visibleSeries.flatMap((item) =>
        item.points.map((point) => Number(point.value)),
    );
    const rawMin = Math.min(...values);
    const rawMax = Math.max(...values);
    const spread = Math.max(rawMax - rawMin, Math.abs(rawMax) * 0.08, 1);
    const min = rawMin - spread * 0.08;
    const max = rawMax + spread * 0.08;
    const dateIndexes = new Map(dates.map((date, index) => [date, index]));
    const x = (date: string) => {
        const index = dateIndexes.get(date) ?? 0;

        return padding.left + (dates.length === 1 ? chartWidth / 2 : (index / (dates.length - 1)) * chartWidth);
    };
    const y = (value: number) => padding.top + ((max - value) / (max - min)) * chartHeight;
    const gridValues = Array.from({ length: 5 }, (_, index) => max - ((max - min) * index) / 4);

    return (
        <div className="space-y-4">
            <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                <svg
                    viewBox={`0 0 ${width} ${height}`}
                    className="min-w-[640px]"
                    role="img"
                    aria-label={`${visibleSeries.map((item) => item.label).join('、')}の評価額推移`}
                >
                    {gridValues.map((gridValue) => {
                        const gridY = y(gridValue);

                        return (
                            <g key={gridValue}>
                                <line
                                    x1={padding.left}
                                    x2={width - padding.right}
                                    y1={gridY}
                                    y2={gridY}
                                    stroke="#e2e8f0"
                                    strokeWidth="1"
                                />
                                <text
                                    x={padding.left - 10}
                                    y={gridY + 4}
                                    textAnchor="end"
                                    className="fill-slate-500 text-[11px]"
                                >
                                    {compactAmount(gridValue, currency)}
                                </text>
                            </g>
                        );
                    })}

                    {visibleSeries.map((item, seriesIndex) => {
                        const color = item.color ?? colors[seriesIndex % colors.length];
                        const points = item.points
                            .map((point) => `${x(point.date)},${y(Number(point.value))}`)
                            .join(' ');

                        return (
                            <g key={item.key}>
                                <polyline
                                    points={points}
                                    fill="none"
                                    stroke={color}
                                    strokeWidth="3"
                                    strokeLinejoin="round"
                                    strokeLinecap="round"
                                />
                                {item.points.length <= 31
                                    ? item.points.map((point) => (
                                        <circle
                                            key={`${item.key}-${point.date}`}
                                            cx={x(point.date)}
                                            cy={y(Number(point.value))}
                                            r="3.5"
                                            fill="white"
                                            stroke={color}
                                            strokeWidth="2"
                                        >
                                            <title>{`${item.label} ${point.date}: ${point.value} ${currency}`}</title>
                                        </circle>
                                    ))
                                    : null}
                            </g>
                        );
                    })}

                    {dates.length === 1 ? (
                        <text
                            x={padding.left + chartWidth / 2}
                            y={height - 10}
                            textAnchor="middle"
                            className="fill-slate-500 text-[11px]"
                        >
                            {dateLabel(dates[0])}
                        </text>
                    ) : (
                        <>
                            <text
                                x={padding.left}
                                y={height - 10}
                                textAnchor="start"
                                className="fill-slate-500 text-[11px]"
                            >
                                {dateLabel(dates[0])}
                            </text>
                            <text
                                x={width - padding.right}
                                y={height - 10}
                                textAnchor="end"
                                className="fill-slate-500 text-[11px]"
                            >
                                {dateLabel(dates[dates.length - 1])}
                            </text>
                        </>
                    )}
                </svg>
            </div>

            <div className="flex flex-wrap gap-x-5 gap-y-2 text-sm text-slate-600">
                {visibleSeries.map((item, index) => {
                    const content = (
                        <>
                        <span
                            className="h-2.5 w-2.5 rounded-full"
                            style={{ backgroundColor: item.color ?? colors[index % colors.length] }}
                        />
                        {item.label}
                        {item.href ? (
                            <span className="text-xs text-indigo-500">詳細 →</span>
                        ) : null}
                        </>
                    );

                    return item.href ? (
                        <Link
                            key={`legend-${item.key}`}
                            href={item.href}
                            className="inline-flex items-center gap-2 rounded-full px-2 py-1 font-medium text-indigo-700 transition hover:bg-indigo-50"
                        >
                            {content}
                        </Link>
                    ) : (
                        <div
                            key={`legend-${item.key}`}
                            className="inline-flex items-center gap-2"
                        >
                            {content}
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
