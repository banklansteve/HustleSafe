<?php

return [
    'budget_deviation_percent' => (int) env('QUEST_PATROL_BUDGET_DEVIATION_PERCENT', 50),
    'boost_spam_window_hours' => (int) env('QUEST_PATROL_BOOST_SPAM_HOURS', 48),
    'boost_spam_threshold' => (int) env('QUEST_PATROL_BOOST_SPAM_THRESHOLD', 3),
    'instant_completion_hours' => (float) env('QUEST_PATROL_INSTANT_COMPLETION_HOURS', 1),
    'duplicate_quest_window_hours' => (int) env('QUEST_PATROL_DUPLICATE_QUEST_HOURS', 72),
    'proposal_velocity_window_hours' => (int) env('QUEST_PATROL_PROPOSAL_VELOCITY_HOURS', 2),
    'proposal_velocity_threshold' => (int) env('QUEST_PATROL_PROPOSAL_VELOCITY_THRESHOLD', 20),
    'win_rate_window_days' => (int) env('QUEST_PATROL_WIN_RATE_DAYS', 7),
    'win_rate_threshold_percent' => (int) env('QUEST_PATROL_WIN_RATE_THRESHOLD', 80),
    'new_account_days' => (int) env('QUEST_PATROL_NEW_ACCOUNT_DAYS', 14),

    // Anti money-laundering / collusion scanning of funded + released quests.
    'laundering_scan_days' => (int) env('QUEST_PATROL_LAUNDERING_SCAN_DAYS', 45),
    'unworked_release_max_messages' => (int) env('QUEST_PATROL_UNWORKED_MAX_MESSAGES', 4),
    'repeat_counterparty_window_days' => (int) env('QUEST_PATROL_REPEAT_COUNTERPARTY_DAYS', 60),
    'repeat_counterparty_threshold' => (int) env('QUEST_PATROL_REPEAT_COUNTERPARTY_THRESHOLD', 3),
    'circular_payment_window_days' => (int) env('QUEST_PATROL_CIRCULAR_PAYMENT_DAYS', 90),
    'dismissal_reasons' => [
        'false_positive' => 'False positive — legitimate transaction',
        'approved_variation' => 'Approved variation — client has special needs',
        'harmless_pattern' => 'Harmless pattern — monitored but not concerning',
        'resolved' => 'Resolved — took action, no further monitoring needed',
    ],
    'admin_boost_reasons' => [
        'promotional_category' => 'Promotional boost for new category',
        'client_retention' => 'Client retention (low proposal volume)',
        'platform_showcase' => 'Platform feature / showcase',
        'other' => 'Other',
    ],
    'revision_issue_types' => [
        'incomplete_description' => 'Incomplete description',
        'missing_deliverables' => 'Missing deliverables detail',
        'unrealistic_budget' => 'Unrealistic budget for scope',
        'budget_category_mismatch' => 'Budget-to-category mismatch',
        'suspicious_patterns' => 'Suspicious patterns',
        'other' => 'Other',
    ],
];
