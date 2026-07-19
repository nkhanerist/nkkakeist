import { TrendSeries } from '@/types/chart';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

type ValueComparisonAreaChartProps = {
    series: TrendSeries[];
    currency: string;
    emptyMessage?: string;
    height?: number;
};

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

export default function ValueComparisonAreaChart({
    series,
    currency,
    emptyMessage,
    height = 260,
}: ValueComparisonAreaChartProps) {
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
                {emptyMessage ?? t('charts.comparison.empty')}
            </div>
        );
    }

    const visibleSeries = availableSeries.filter((item) =>
        visibleKeys.has(item.key),
    );
    const areaSeries = [...visibleSeries].sort((left, right) => {
        if (left.key.endsWith(':acquisition-cost')) {
            return -1;
        }

        if (right.key.endsWith(':acquisition-cost')) {
            return 1;
        }

        return 0;
    });
    const dates = Array.from(
        new Set(
            availableSeries.flatMap((item) =>
                item.points.map((point) => point.date),
            ),
        ),
    ).sort();
    const width = 920;
    const padding = { top: 20, right: 24, bottom: 38, left: 82 };
    const chartWidth = width - padding.left - padding.right;
    const chartHeight = height - padding.top - padding.bottom;
    const dateIndexes = new Map(dates.map((date, index) => [date, index]));
    const x = (date: string) => {
        const index = dateIndexes.get(date) ?? 0;

        return (
            padding.left +
            (dates.length === 1
                ? chartWidth / 2
                : (index / (dates.length - 1)) * chartWidth)
        );
    };
    const values = visibleSeries.flatMap((item) =>
        item.points.map((point) => Number(point.value)).filter(Number.isFinite),
    );
    const rawMin = values.length > 0 ? Math.min(...values) : 0;
    const rawMax = values.length > 0 ? Math.max(...values) : 1;
    const spread = Math.max(rawMax - rawMin, Math.abs(rawMax) * 0.08, 1);
    const min = rawMin - spread * 0.08;
    const max = rawMax + spread * 0.08;
    const y = (value: number) =>
        padding.top + ((max - value) / (max - min)) * chartHeight;
    const gridValues = Array.from(
        { length: 5 },
        (_, index) => max - ((max - min) * index) / 4,
    );
    const valuationSeries = availableSeries.find((item) =>
        item.key.endsWith(':valuation'),
    );
    const acquisitionSeries = availableSeries.find((item) =>
        item.key.endsWith(':acquisition-cost'),
    );
    const showDifference =
        valuationSeries !== undefined &&
        acquisitionSeries !== undefined &&
        visibleKeys.has(valuationSeries.key) &&
        visibleKeys.has(acquisitionSeries.key);
    const valuationPoints = new Map(
        valuationSeries?.points.map((point) => [
            point.date,
            Number(point.value),
        ]) ?? [],
    );
    const acquisitionPoints = new Map(
        acquisitionSeries?.points.map((point) => [
            point.date,
            Number(point.value),
        ]) ?? [],
    );
    const commonDates = dates.filter(
        (date) => valuationPoints.has(date) && acquisitionPoints.has(date),
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
                    aria-label={t('charts.comparison.selectorAria')}
                >
                    {availableSeries.map((item) => {
                        const isVisible = visibleKeys.has(item.key);
                        const color = item.color ?? '#4f46e5';

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
                        {t('charts.comparison.showAll')}
                    </button>
                    <button
                        type="button"
                        onClick={() => setVisibleKeys(new Set())}
                        className="rounded-md px-2 py-1 text-slate-500 hover:bg-slate-100"
                    >
                        {t('charts.comparison.clearAll')}
                    </button>
                </div>
            </div>

            {visibleSeries.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-300 bg-white px-4 py-10 text-center text-sm text-slate-500">
                    {t('charts.comparison.selectPrompt')}
                </div>
            ) : (
                <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white p-3">
                    <svg
                        viewBox={`0 0 ${width} ${height}`}
                        className="min-w-[640px]"
                        role="img"
                        aria-label={t('charts.comparison.aria', {
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

                        {areaSeries.map((item) => {
                            const color = item.color ?? '#4f46e5';
                            const linePoints = item.points
                                .map(
                                    (point) =>
                                        `${x(point.date)},${y(Number(point.value))}`,
                                )
                                .join(' ');
                            const firstPoint = item.points[0];
                            const lastPoint =
                                item.points[item.points.length - 1];
                            const areaPoints =
                                firstPoint && lastPoint
                                    ? `${x(firstPoint.date)},${padding.top + chartHeight} ${linePoints} ${x(lastPoint.date)},${padding.top + chartHeight}`
                                    : '';

                            return areaPoints === '' ? null : (
                                <polygon
                                    key={`area-${item.key}`}
                                    points={areaPoints}
                                    fill={color}
                                    fillOpacity={
                                        item.key.endsWith(':acquisition-cost')
                                            ? 0.1
                                            : 0.14
                                    }
                                />
                            );
                        })}

                        {showDifference
                            ? commonDates.slice(1).map((date, index) => {
                                  const previousDate = commonDates[index];
                                  const valuation =
                                      valuationPoints.get(date) ?? 0;
                                  const previousValuation =
                                      valuationPoints.get(previousDate) ?? 0;
                                  const acquisition =
                                      acquisitionPoints.get(date) ?? 0;
                                  const previousAcquisition =
                                      acquisitionPoints.get(previousDate) ?? 0;
                                  const isGain =
                                      (valuation -
                                          acquisition +
                                          (previousValuation -
                                              previousAcquisition)) /
                                          2 >=
                                      0;

                                  return (
                                      <polygon
                                          key={`difference-${previousDate}-${date}`}
                                          points={`${x(previousDate)},${y(previousValuation)} ${x(date)},${y(valuation)} ${x(date)},${y(acquisition)} ${x(previousDate)},${y(previousAcquisition)}`}
                                          fill={isGain ? '#10b981' : '#f43f5e'}
                                          fillOpacity="0.2"
                                      />
                                  );
                              })
                            : null}

                        {visibleSeries.map((item) => {
                            const color = item.color ?? '#4f46e5';
                            const points = item.points
                                .map(
                                    (point) =>
                                        `${x(point.date)},${y(Number(point.value))}`,
                                )
                                .join(' ');

                            return (
                                <g key={`line-${item.key}`}>
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

            {showDifference && commonDates.length >= 2 ? (
                <p className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>
                        <span className="mr-1 text-emerald-500">■</span>
                        {t('charts.comparison.gainBand')}
                    </span>
                    <span>
                        <span className="mr-1 text-rose-500">■</span>
                        {t('charts.comparison.lossBand')}
                    </span>
                </p>
            ) : null}
        </div>
    );
}
