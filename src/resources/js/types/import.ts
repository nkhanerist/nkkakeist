export type ImportAccountOption = {
    id: number;
    name: string;
    type: string;
    currency: string;
    balance_role: 'asset' | 'liability' | 'clearing';
    balance_method: 'ledger' | 'snapshot';
    is_active: boolean;
};

export type ImportSourceOption = {
    value: string;
    label: string;
};

export type ImportListItem = {
    id: number;
    source_name: string | null;
    source_label: string;
    source_metadata: Record<string, unknown> | null;
    original_filename: string;
    status: string;
    status_label: string;
    total_rows: number;
    imported_rows: number;
    skipped_rows: number;
    duplicate_rows: number;
    error_message: string | null;
    imported_at: string | null;
    created_at: string | null;
    account: { id: number; name: string; currency: string } | null;
};

export type TransferResolution = {
    source_resolution_type: string | null;
    source_resolution_message: string | null;
    destination_resolution_type: string | null;
    destination_resolution_message: string | null;
    unresolved_reason: string | null;
};

export type ImportPreviewRow = {
    id: number;
    row_number: number;
    transaction_date: string | null;
    amount: string | null;
    account_name: string | null;
    category_name: string | null;
    subcategory_name: string | null;
    merchant_name: string | null;
    description: string | null;
    detected_type: string | null;
    is_calculation_target: boolean | null;
    affects_account_balance: boolean | null;
    resolved_account: { id: number; name: string; currency: string } | null;
    manual_resolved_account_id: number | null;
    resolved_transfer_account: { id: number; name: string; currency: string } | null;
    manual_resolved_transfer_account_id: number | null;
    resolved_category: { id: number; name: string } | null;
    resolved_subcategory: { id: number; name: string } | null;
    matched_classification_rule: { id: number; name: string; priority: number } | null;
    rule_applied_fields: string[];
    category_resolution_source: 'csv' | 'rule' | null;
    subcategory_resolution_source: 'csv' | 'rule' | null;
    calculation_target_source: 'csv' | 'rule' | null;
    status: string;
    is_duplicate_candidate: boolean;
    duplicate_hash: string | null;
    validation_errors: string[];
    raw_payload: Record<string, unknown>;
    transfer_resolution: TransferResolution;
};

export type JrePointReconciliation = {
    captured_at: string;
    official_total: string;
    official_regular: string;
    official_limited: string;
    nearest_expiry: string | null;
    ledger_balance_before_import: string;
    import_balance_change: string;
    expected_balance_after_import: string;
    difference: string;
    is_initial_import: boolean;
    recommended_initial_balance: string | null;
};

export type PaginatedImports = {
    data: ImportListItem[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
};
