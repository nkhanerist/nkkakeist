export type DashboardCurrencySummary = {
    currency: string;
    income_total: string;
    expense_total: string;
    balance_total: string;
};

export type DashboardPeriodOption = {
    value: string;
    label: string;
};

export type DashboardAccountSummary = {
    id: number;
    name: string;
    type: string;
    currency: string;
    initial_balance: string;
    current_balance: string;
    is_active: boolean;
};

export type DashboardCategoryExpense = {
    id: number | null;
    name: string;
    currency: string;
    total_amount: string;
};

export type DashboardYearlyCategoryExpenseGroup = {
    currency: string;
    items: {
        category_id: number | null;
        category_name: string;
        total_amount: string;
    }[];
};

export type DashboardMonthlyTrend = {
    month: string;
    label: string;
    summaries: DashboardCurrencySummary[];
};

export type DashboardYearlyTrend = DashboardMonthlyTrend;
