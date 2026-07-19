<?php

return [
    'transaction_types' => [
        'any' => 'All',
        'income' => 'Income',
        'expense' => 'Expense',
        'transfer' => 'Transfer',
    ],
    'match_fields' => [
        'merchant_name' => 'Description / Merchant',
        'description' => 'Description',
        'account_name' => 'Account Name',
    ],
    'match_operators' => [
        'contains' => 'Contains',
        'equals' => 'Exact Match',
        'starts_with' => 'Starts With',
    ],
    'fields' => [
        'name' => 'rule name',
        'transaction_type' => 'transaction type',
        'match_field' => 'matching field',
        'match_operator' => 'matching condition',
        'match_value' => 'matching value',
        'category_id' => 'category',
        'subcategory_id' => 'subcategory',
        'is_calculation_target' => 'reporting flag',
        'priority' => 'priority',
        'is_active' => 'status',
    ],
    'messages' => [
        'category_required_for_subcategory' => 'Select a category before selecting a subcategory.',
        'subcategory_mismatch' => 'The selected subcategory does not belong to the selected category.',
        'completion_required' => 'Set at least one of category, subcategory, or reporting flag.',
        'any_requires_both_category' => 'Rules for all transaction types can only use categories whose type is both.',
        'category_type_mismatch' => 'The selected category type does not match the rule transaction type.',
    ],
];
