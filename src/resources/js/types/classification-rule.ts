export type ClassificationRuleListItem = {
    id: number;
    name: string;
    transaction_type: string | null;
    transaction_type_label: string;
    match_field: string;
    match_field_label: string;
    match_operator: string;
    match_operator_label: string;
    match_value: string;
    category: { id: number; name: string } | null;
    subcategory: { id: number; name: string } | null;
    is_calculation_target: boolean | null;
    priority: number;
    is_active: boolean;
};

export type ClassificationRuleOption = {
    value: string;
    label: string;
};

export type ClassificationRuleCategoryOption = {
    id: number;
    name: string;
    type: string;
    is_active: boolean;
};

export type ClassificationRuleSubcategoryOption = {
    id: number;
    category_id: number;
    name: string;
    is_active: boolean;
};
