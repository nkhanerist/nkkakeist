export type DashboardCurrencySummary = {
    currency: string;
    income_total: string;
    expense_total: string;
    balance_total: string;
};

export type DashboardMonthlyReportComparison = {
    label: string;
    income_total: string;
    expense_total: string;
    balance_total: string;
    income_change_amount: string;
    income_change_percent: string | null;
    expense_change_amount: string;
    expense_change_percent: string | null;
    balance_change_amount: string;
};

export type DashboardMonthlyReportComparisonGroup = {
    currency: string;
    current: DashboardCurrencySummary;
    previous_month: DashboardMonthlyReportComparison;
    previous_year: DashboardMonthlyReportComparison;
};

export type DashboardMonthlyReportActivityGroup = {
    currency: string;
    transaction_count: number;
    expense_count: number;
    average_expense: string | null;
    largest_expense: string | null;
};

export type DashboardMonthlyReportMerchant = {
    currency: string;
    name: string;
    keyword: string | null;
    total_amount: string;
    transaction_count: number;
};

export type DashboardMonthlyReportNetWorthChange = {
    currency: string;
    start_date: string | null;
    end_date: string | null;
    start_net_worth: string | null;
    end_net_worth: string | null;
    change_amount: string | null;
};

export type DashboardMonthlyReportCategoryExpenseGroup = {
    currency: string;
    previous_month_label: string;
    current_total: string;
    previous_total: string;
    change_amount: string;
    items: {
        category_id: number | null;
        category_name: string;
        current_amount: string;
        previous_amount: string;
        change_amount: string;
        current_share_percent: string | null;
    }[];
};

export type DashboardMonthlyClosing = {
    status: 'open' | 'reviewed' | 'closed';
    status_label: string;
    note: string;
    reviewed_at: string | null;
    closed_at: string | null;
    last_reopened_at: string | null;
    last_reopen_reason: string | null;
    has_changes_since_review: boolean;
    month_ended: boolean;
    can_close: boolean;
    blockers: string[];
    accounts: {
        id: number;
        name: string;
        type: string;
        currency: string;
        state: 'unconfirmed' | 'confirmed' | 'changed';
        confirmed_at: string | null;
    }[];
};

export type DashboardMonthlyReport = {
    comparison_groups: DashboardMonthlyReportComparisonGroup[];
    activity_groups: DashboardMonthlyReportActivityGroup[];
    top_merchants: DashboardMonthlyReportMerchant[];
    category_expense_groups: DashboardMonthlyReportCategoryExpenseGroup[];
    quality: {
        uncategorized_count: number;
        unconfirmed_count: number;
        pending_import_count: number;
    };
    closing: DashboardMonthlyClosing;
    net_worth_changes: DashboardMonthlyReportNetWorthChange[];
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

export type DashboardNetWorthTrend = {
    currency: string;
    points: {
        date: string;
        assets: string;
        liabilities: string;
        net_worth: string;
    }[];
};

export type DashboardAssetHistoryTrend = {
    currency: string;
    source_name: string;
    points: {
        date: string;
        total_assets: string;
        breakdown: Record<string, string>;
    }[];
};

export type DashboardDailySnapshotStatus = {
    date: string;
    state: 'complete' | 'partial' | 'missing';
    account_count: number;
    position_count: number;
    asset_history_recorded: boolean;
    last_imported_at: string | null;
    required_account_count: number;
    updated_account_count: number;
    coverage_started_on: string | null;
    accounts: {
        id: number;
        name: string;
        type: 'bank' | 'credit_card' | 'securities';
        state: 'updated' | 'stale';
        latest_snapshot_date: string;
    }[];
    coverage_days: {
        date: string;
        state: 'complete' | 'partial' | 'missing' | 'not_required';
        updated_account_count: number;
        required_account_count: number;
        position_count: number;
        asset_history_recorded: boolean;
    }[];
    recent_failures: {
        id: number;
        source_name: string;
        original_filename: string;
        failed_at: string;
        error_message: string | null;
    }[];
};

export type DashboardWeeklyImportSourceStatus = {
    state: 'updated' | 'stale' | 'missing';
    last_updated_at: string | null;
    latest_history_date: string | null;
};

export type DashboardWeeklyImportStatus = {
    week_start: string;
    week_end: string;
    sources: {
        jre_point: DashboardWeeklyImportSourceStatus;
        mobile_suica: DashboardWeeklyImportSourceStatus;
    };
};
