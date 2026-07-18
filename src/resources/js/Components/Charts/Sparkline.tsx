import { TrendPoint } from '@/types/chart';

type SparklineProps = {
    points: TrendPoint[];
    tone?: 'indigo' | 'emerald' | 'rose';
};

const tones = {
    indigo: '#4f46e5',
    emerald: '#059669',
    rose: '#e11d48',
};

export default function Sparkline({ points, tone = 'indigo' }: SparklineProps) {
    if (points.length === 0) {
        return <span className="text-xs text-slate-400">データなし</span>;
    }

    const width = 180;
    const height = 48;
    const values = points.map((point) => Number(point.value));
    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = Math.max(max - min, 1);
    const coordinates = points.map((point, index) => {
        const x = points.length === 1 ? width / 2 : (index / (points.length - 1)) * width;
        const y = 5 + ((max - Number(point.value)) / range) * (height - 10);

        return `${x},${y}`;
    });

    return (
        <svg
            viewBox={`0 0 ${width} ${height}`}
            className="h-12 w-44"
            role="img"
            aria-label="評価額の小型推移グラフ"
        >
            <polyline
                points={coordinates.join(' ')}
                fill="none"
                stroke={tones[tone]}
                strokeWidth="3"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
            {points.length === 1 ? (
                <circle
                    cx={width / 2}
                    cy={height / 2}
                    r="4"
                    fill="white"
                    stroke={tones[tone]}
                    strokeWidth="3"
                />
            ) : null}
        </svg>
    );
}
