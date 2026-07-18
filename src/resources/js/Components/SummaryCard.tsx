type SummaryCardProps = {
    label: string;
    value: string;
    tone?: 'default' | 'positive' | 'negative';
};

const toneClasses: Record<NonNullable<SummaryCardProps['tone']>, string> = {
    default: 'border-slate-200 bg-white text-slate-900',
    positive: 'border-emerald-200 bg-emerald-50 text-emerald-900',
    negative: 'border-rose-200 bg-rose-50 text-rose-900',
};

export default function SummaryCard({
    label,
    value,
    tone = 'default',
}: SummaryCardProps) {
    return (
        <div className={`rounded-2xl border p-5 shadow-sm ${toneClasses[tone]}`}>
            <p className="text-sm font-medium text-slate-500">{label}</p>
            <p className="mt-3 text-2xl font-semibold tracking-tight">{value}</p>
        </div>
    );
}
