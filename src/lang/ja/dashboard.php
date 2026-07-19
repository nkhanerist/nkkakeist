<?php

return [
    'period' => [
        'year' => ':year年',
        'month' => ':year年:month',
    ],
    'months' => [
        1 => '1月',
        2 => '2月',
        3 => '3月',
        4 => '4月',
        5 => '5月',
        6 => '6月',
        7 => '7月',
        8 => '8月',
        9 => '9月',
        10 => '10月',
        11 => '11月',
        12 => '12月',
    ],
    'report' => [
        'uncategorized' => 'カテゴリ未設定',
        'unnamed_merchant' => '名称なし',
    ],
    'closing' => [
        'status' => [
            'open' => '受付中',
            'reviewed' => 'レポート確認済み',
            'closed' => '締め済み',
        ],
        'blockers' => [
            'month_not_ended' => '対象月がまだ終了していません。',
            'uncategorized' => 'カテゴリなしの取引が:count件あります。',
            'unconfirmed' => '未確認取引が:count件あります。',
            'pending_imports' => '確認待ちの取込が:count件あります。',
            'no_accounts' => '月次締め確認の対象口座が設定されていません。',
            'unconfirmed_accounts' => '月末反映が未確認の口座が:count件あります。',
            'changed_accounts' => '確認後にデータが変わった口座が:count件あります。',
            'report_not_reviewed' => '月次レポート全体を確認済みにしてください。',
            'changed_after_review' => '月次内容の確認後にデータが変更されています。',
        ],
        'errors' => [
            'closed_requires_reopen' => '締め済みの月は、先に締めを解除してください。',
            'account_not_required' => 'この口座は月次締め確認の対象ではありません。',
            'only_closed_can_reopen' => '締め済みの月だけ解除できます。',
        ],
        'messages' => [
            'note_saved' => '月次メモを保存しました。',
            'reviewed' => '月次レポート全体を確認済みにしました。',
            'account_confirmed' => ':accountの月末反映を確認済みにしました。',
            'account_unconfirmed' => ':accountの確認を取り消しました。',
            'closed' => ':monthを締め済みにしました。',
            'reopened' => ':monthの締めを解除しました。',
        ],
    ],
];
