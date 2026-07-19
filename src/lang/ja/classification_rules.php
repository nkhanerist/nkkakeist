<?php

return [
    'transaction_types' => [
        'any' => 'すべて',
        'income' => '収入',
        'expense' => '支出',
        'transfer' => '振替',
    ],
    'match_fields' => [
        'merchant_name' => '摘要 / 店舗名',
        'description' => '説明',
        'account_name' => '口座名',
    ],
    'match_operators' => [
        'contains' => '含む',
        'equals' => '完全一致',
        'starts_with' => '前方一致',
    ],
    'fields' => [
        'name' => 'ルール名',
        'transaction_type' => '取引種別',
        'match_field' => '対象フィールド',
        'match_operator' => '一致条件',
        'match_value' => '一致値',
        'category_id' => 'カテゴリ',
        'subcategory_id' => '小分類',
        'is_calculation_target' => '集計対象フラグ',
        'priority' => '優先度',
        'is_active' => '状態',
    ],
    'messages' => [
        'category_required_for_subcategory' => '小分類を選択する場合はカテゴリを選択してください。',
        'subcategory_mismatch' => '選択した小分類はカテゴリに属していません。',
        'completion_required' => 'カテゴリ・小分類・集計対象フラグのいずれか1つ以上を設定してください。',
        'any_requires_both_category' => '取引種別が「すべて」のルールには、種別が both のカテゴリだけを指定できます。',
        'category_type_mismatch' => '選択したカテゴリの種別がルールの取引種別と一致していません。',
    ],
];
