<?php

return [
    'period' => [
        'year' => ':year',
        'month' => ':month :year',
    ],
    'months' => [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December',
    ],
    'report' => [
        'uncategorized' => 'Uncategorized',
        'unnamed_merchant' => 'Unnamed',
    ],
    'closing' => [
        'status' => [
            'open' => 'Open',
            'reviewed' => 'Report Reviewed',
            'closed' => 'Closed',
        ],
        'blockers' => [
            'month_not_ended' => 'The selected month has not ended yet.',
            'uncategorized' => 'There are :count uncategorized transactions.',
            'unconfirmed' => 'There are :count unconfirmed transactions.',
            'pending_imports' => 'There are :count imports awaiting review.',
            'no_accounts' => 'No accounts are configured for monthly close review.',
            'unconfirmed_accounts' => 'There are :count accounts with unconfirmed month-end updates.',
            'changed_accounts' => 'There are :count accounts whose data changed after confirmation.',
            'report_not_reviewed' => 'Mark the entire monthly report as reviewed.',
            'changed_after_review' => 'Data changed after the monthly report was reviewed.',
        ],
        'errors' => [
            'closed_requires_reopen' => 'Reopen the closed month before making this change.',
            'account_not_required' => 'This account is not included in monthly close review.',
            'only_closed_can_reopen' => 'Only a closed month can be reopened.',
        ],
        'messages' => [
            'note_saved' => 'Monthly note saved.',
            'reviewed' => 'Monthly report marked as reviewed.',
            'account_confirmed' => 'Month-end update confirmed for :account.',
            'account_unconfirmed' => 'Confirmation removed for :account.',
            'closed' => ':month closed.',
            'reopened' => ':month reopened.',
        ],
    ],
];
