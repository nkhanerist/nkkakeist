export type TransactionTypeOption = {
    value: string;
    label: string;
};

export type TransactionAccountOption = {
    id: number;
    name: string;
    type: string;
    currency: string;
    is_active: boolean;
};

export type TransactionCategoryOption = {
    id: number;
    name: string;
    type: string;
    is_active: boolean;
};

export type TransactionSubcategoryOption = {
    id: number;
    category_id: number;
    name: string;
    is_active: boolean;
};

export type TransactionFormValues = {
    transaction_date: string;
    type: string;
    account_id: string;
    transfer_account_id: string;
    amount: string;
    currency: string;
    merchant_name: string;
    description: string;
    category_id: string;
    subcategory_id: string;
    payment_method_label: string;
    is_confirmed: boolean;
    is_calculation_target: boolean;
    affects_account_balance: boolean;
    memo: string;
};

export type EditableTransaction = {
    id: number;
    transaction_date: string;
    type: string;
    account_id: number;
    transfer_account_id: number | null;
    amount: string;
    currency: string;
    merchant_name: string | null;
    description: string | null;
    category_id: number | null;
    subcategory_id: number | null;
    payment_method_label: string | null;
    is_confirmed: boolean;
    is_calculation_target: boolean;
    affects_account_balance: boolean;
    memo: string | null;
};

export type TransactionListItem = {
    id: number;
    transaction_date: string;
    type: string;
    type_label: string;
    account: { id: number; name: string } | null;
    transfer_account: { id: number; name: string } | null;
    amount: string;
    currency: string;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
    merchant_name: string | null;
    description: string | null;
    payment_method_label: string | null;
    memo: string | null;
    is_confirmed: boolean;
    is_calculation_target: boolean;
    affects_account_balance: boolean;
};

export type TransactionFilters = {
    date_from: string;
    date_to: string;
    account_id: string;
    category_id: string;
    category_state: 'all' | 'categorized' | 'uncategorized';
    currency: string;
    type: string;
    keyword: string;
    is_confirmed: string;
    calculation_target: 'all' | 'included' | 'excluded';
    sort: 'date' | 'amount' | 'account' | 'category' | 'summary';
    direction: 'asc' | 'desc';
    filter_panel: 'expanded' | 'collapsed';
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type PaginatedTransactions = {
    data: TransactionListItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: PaginationLink[];
};

export type TransactionCategoryReviewFilters = {
    status: 'high' | 'manual' | 'all';
    type: 'all' | 'expense' | 'income';
};

export type TransactionCategorySuggestion = {
    transaction_id: number;
    user_id: number;
    type: 'expense' | 'income';
    transaction_date: string;
    amount: string;
    currency: string;
    account_name: string | null;
    merchant_name: string | null;
    description: string | null;
    payment_method_label: string | null;
    memo: string | null;
    is_confirmed: boolean;
    suggested_category_id: number | null;
    suggested_category: string | null;
    suggested_subcategory_id: number | null;
    suggested_subcategory: string | null;
    confidence: number;
    reason: string;
    reference_count: number;
    reference_transaction_id: number | null;
    matched_classification_rule_id: number | null;
};

export type TransactionCategoryReview = {
    items: TransactionCategorySuggestion[];
    summary: {
        total: number;
        high_confidence: number;
        manual_review: number;
        displayed: number;
    };
    has_more: boolean;
};

export type TransactionDetail = {
    id: number;
    transaction_date: string;
    posted_at: string | null;
    type: string;
    type_label: string;
    amount: string;
    currency: string;
    merchant_name: string | null;
    description: string | null;
    payment_method_label: string | null;
    memo: string | null;
    is_confirmed: boolean;
    is_calculation_target: boolean;
    affects_account_balance: boolean;
    account: { id: number; name: string; type: string } | null;
    transfer_account: { id: number; name: string; type: string } | null;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
};
