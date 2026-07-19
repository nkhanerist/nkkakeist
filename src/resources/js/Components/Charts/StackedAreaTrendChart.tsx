import { TrendSeries } from '@/types/chart';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type StackedAreaTrendChartProps = {
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
    '#be185d',
    '#65a30d',
    '#0369a1',
    '#9333ea',
];

function compactAmount(
    value: number,
    currency: string,
    locale: string,
): string {
    return `${new Intl.NumberFormat(locale, {
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value)} ${currency}`;
}

function dateLabel(date: string): string {
    const [, month, day] = date.split('-');

    return `${Number(month)}/${Number(day)}`;
}

export default function StackedAreaTrendChart({
    series,
    currency,
    emptyMessage,
    height = 300,
}: StackedAreaTrendChartProps) {
    const { t, i18n } = useTranslation('securities');
    const numberLocale = i18n.language.startsWith('en') ? 'en-US' : 'ja-JP';
    const availableSeries = useMemo(
        () => series.filter((item) => item.points.length > 0),
        [series],
    );
    const seriesSignature = availableSeries.map((item) => item.key).join('|');
    const [visibleKeys, setVisibleKeys] = useState<Set<string>>(
        () => new Set(availableSeries.map((item) => item.key)),
    );

    useEffect(() => {
        setVisibleKeys(new Set(availableSeries.map((item) => item.key)));
    }, [seriesSignature]);

    if (availableSeries.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                {emptyMessage ?? t('charts.stacked.empty')}
            </div>
        );
    }

    const visibleSeries = availableSeries.filter((item) =>
        visibleKeys.has(item.key),
    );
    const dates = Array.from(
        new Set(
            availableSeries.flatMap((item) =>
                item.points.map((point) => point.date),
            ),
        ),
    ).sort();
    const pointMaps = new Map(
        availableSeries.map((item) => [
            item.key,
            new Map(
                item.points.map((point) => [point.date, Number(point.value)]),
            ),
        ]),
    );
    let previousUpper = dates.map(() => 0);
    const layers = visibleSeries.map((item) => {
        const lower = [...previousUpper];
        const values = dates.map(
            (date) => pointMaps.get(item.key)?.get(date) ?? 0,
        );
        const upper = lower.map((value, index) => value + values[index]);

        previousUpper = upper;

        return { item, lower, upper, values };
    });
    const maxTotal = Math.max(...previousUpper, 1);
    const chartMax = maxTotal * 1.08;
    const width = 920;
    const padding = { top: 20, right: 24, bottom: 38, left: 82 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const x = (index: number) =>
        padding.left +
        (dates.length === 1
            ? chartWidth / 2
            : (index / (dates.length - 1)) * chartWidth);
    const y = (value: number) =>
        padding.top + ((chartMax - value) / chartMax) * chartHeight;
    const gridValues = Array.from(
        { length: 5 },
        (_, index) => chartMax - (chartMax * index) / 4,
    );
    const toggleSeries = (key: string) => {
        setVisibleKeys((current) => {
            const next = new Set(current);

            if (next.has(key)) {
                next.delete(key);
            } else {
                next.add(key);
            }

            return next;
        });
    };

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div
                    className="flex flex-wrap gap-2"
                    aria-label={t('charts.stacked.selectorAria')}
                >
                    {availableSeries.map((item, index) => {
                        const isVisible = visibleKeys.has(item.key);
                        const color =
                            item.color ?? colors[index % colors.length];

                        return (
                            <button
                                key={item.key}
                                type="button"
                                aria-pressed={isVisible}
                                onClick={() => toggleSeries(item.key)}
                                className={`inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-sm font-medium transition ${
                                    isVisible
                                        ? 'border-slate-300 bg-white text-slate-800 shadow-sm'
                                        : 'border-slate-200 bg-slate-100 text-slate-400'
                                }`}
                            >
                                <span
                                    className="h-2.5 w-2.5 rounded-full"
                                    style={{
                                        backgroundColor: isVisible
                                            ? color
                                            : '#cbd5e1',
                                    }}
                                />
                                <span
                                    className={isVisible ? '' : 'line-through'}
                                >
                                    {item.label}
                                </span>
                            </button>
                        );
                    })}
                </div>
                <div className="flex gap-2 text-xs font-medium">
                    <button
                        type="button"
                        onClick={() =>
                            setVisibleKeys(
                                new Set(
                                    availableSeries.map((item) => item.key),
                                ),
                            )
                        }
                        className="rounded-md px-2 py-1 text-indigo-700 hover:bg-indigo-50"
                    >
                        {t('charts.stacked.showAll')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setVisibleKeys(new Set())}
                        className="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100"
                    >
                        {t('charts.stacked.clearAll')}
                    </button>
                </div>
            </div>

            {visibleSeries.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    {t('charts.stacked.selectPrompt')}
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        className="min-w-[640px]"
                        role="img"
                        aria-label={t('charts.stacked.aria', {
                            series: visibleSeries
                                .map((item) => item.label)
                                .join(t('charts.seriesSeparator')),
                        })}
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
                                        {compactAmount(
                                            gridValue,
                                            currency,
                                            numberLocale,
                                        )}
                                    </text>
                                </g>
                            );
                        })}

                        {layers.map((layer) => {
                            const originalIndex = availableSeries.findIndex(
                                (candidate) => candidate.key === layer.item.key,
                            );
                            const color =
                                layer.item.color ??
                                colors[originalIndex % colors.length];
                            const upperPoints = layer.upper
                                .map(
                                    (value, index) => `${x(index)},${y(value)}`,
                                )
                                .join(' ');
                            const lowerPoints = [...layer.lower]
                                .reverse()
                                .map((value, reverseIndex) => {
                                    const index =
                                        layer.lower.length - reverseIndex - 1;

                                    return `${x(index)},${y(value)}`;
                                })
                                .join(' ');

                            return (
                                <g key={layer.item.key}>
                                    <polygon
                                        points={`${upperPoints} ${lowerPoints}`}
                                        fill={color}
                                        fillOpacity="0.72"
                                        stroke={color}
                                        strokeWidth="1.5"
                                        strokeLinejoin="round"
                                    />
                                    {dates.length <= 31
                                        ? dates.map((date, index) => (
                                              <circle
                                                  key={`${layer.item.key}-${date}`}
                                                  cx={x(index)}
                                                  cy={y(layer.upper[index])}
                                                  r="3"
                                                  fill={color}
                                              >
                                                  <title>{`${layer.item.label} ${date}: ${layer.values[index]} ${currency}`}</title>
                                              </circle>
                                          ))
                                        : null}
                                </g>
                            );
                        })}

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
                    </svg>
                </div>
            )}

            <p className="text-xs text-slate-500">
                {t('charts.stacked.description')}
            </p>
        </div>
    );
}
