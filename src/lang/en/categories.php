<?php

return [
    'types' => [
        'income' => 'Income',
        'expense' => 'Expense',
        'both' => 'Both',
    ],
    'fields' => [
        'name' => 'category name',
        'type' => 'type',
        'color' => 'color',
        'icon' => 'icon name',
        'display_order' => 'display order',
        'is_active' => 'status',
        'return_to' => 'return destination',
        'review_status' => 'review status',
        'review_type' => 'transaction type',
    ],
    'subcategory_fields' => [
        'category_id' => 'category',
        'name' => 'subcategory name',
        'display_order' => 'display order',
        'is_active' => 'status',
    ],
    'messages' => [
        'created_for_review' => 'Added category “:name”. Select it for the relevant transaction.',
    ],
];
