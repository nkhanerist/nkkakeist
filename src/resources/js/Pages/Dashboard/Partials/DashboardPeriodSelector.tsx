import { DashboardPeriodOption } from '@/types/dashboard';

type DashboardPeriodSelectorProps = {
    selectedView: 'month' | 'year';
    selectedYear: string;
    selectedMonth: string;
    yearOptions: DashboardPeriodOption[];
    monthOptions: DashboardPeriodOption[];
    processing: boolean;
    onChangeView: (view: 'month' | 'year') => void;
    onChangeYear: (year: string) => void;
    onChangeMonth: (month: string) => void;
    onSubmit: () => void;
};

export default function DashboardPeriodSelector({
    selectedView,
    selectedYear,
    selectedMonth,
    yearOptions,
    monthOptions,
    processing,
    onChangeView,
    onChangeYear,
    onChangeMonth,
    onSubmit,
}: DashboardPeriodSelectorProps) {
    return (
        <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-slate-50 p-5 lg:flex-row lg:items-end lg:justify-between">
            <div className="space-y-4">
                <div>
                    <p className="text-sm font-medium text-slate-700">表示単位</p>
                    <div className="mt-2 inline-flex rounded-lg border border-slate-300 bg-white p-1">
                        {(['month', 'year'] as const).map((view) => (
                            <button
                                key={view}
                                type="button"
                                onClick={() => onChangeView(view)}
                                className={`rounded-md px-4 py-2 text-sm font-medium transition ${
                                    selectedView === view
                                        ? 'bg-indigo-600 text-white'
                                        : 'text-slate-700 hover:bg-slate-100'
                                }`}
                            >
                                {view === 'month' ? '月次' : '年次'}
                            </button>
                        ))}
                    </div>
                </div>

                <div className="flex flex-wrap gap-3">
                    <div>
                        <label
                            htmlFor="dashboard-year"
                            className="text-sm font-medium text-slate-700"
                        >
                            年
                        </label>
                        <select
                            id="dashboard-year"
                            value={selectedYear}
                            onChange={(event) => onChangeYear(event.target.value)}
                            className="mt-1 block w-32 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            {yearOptions.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>

                    {selectedView === 'month' ? (
                        <div>
                            <label
                                htmlFor="dashboard-month"
                                className="text-sm font-medium text-slate-700"
                            >
                                月
                            </label>
                            <select
                                id="dashboard-month"
                                value={selectedMonth}
                                onChange={(event) => onChangeMonth(event.target.value)}
                                className="mt-1 block w-28 rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                {monthOptions.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    ) : null}
                </div>
            </div>

            <button
                type="button"
                disabled={processing}
                onClick={onSubmit}
                className="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
                表示を更新
            </button>
        </div>
    );
}
