<?php

return [
    'types' => [
        'income' => '収入',
        'expense' => '支出',
        'both' => '両方',
    ],
    'fields' => [
        'name' => 'カテゴリ名',
        'type' => '種別',
        'color' => '色',
        'icon' => 'アイコン名',
        'display_order' => '表示順',
        'is_active' => '状態',
        'return_to' => '戻り先',
        'review_status' => '確認状態',
        'review_type' => '取引種別',
    ],
    'subcategory_fields' => [
        'category_id' => 'カテゴリ',
        'name' => '小分類名',
        'display_order' => '表示順',
        'is_active' => '状態',
    ],
    'messages' => [
        'created_for_review' => 'カテゴリ「:name」を追加しました。対象の取引で選択してください。',
    ],
];
