<?php

return [
    'escrow_max_frozen_days' => (int) env('OPERATIONS_ESCROW_MAX_FROZEN_DAYS', 14),
    'escrow_no_start_work_days' => (int) env('OPERATIONS_ESCROW_NO_START_DAYS', 3),
    'escrow_client_review_stale_days' => (int) env('OPERATIONS_ESCROW_REVIEW_STALE_DAYS', 7),
    'escrow_milestone_overdue_days' => (int) env('OPERATIONS_ESCROW_MILESTONE_OVERDUE_DAYS', 5),

    'manual_badge_slugs' => [
        'top-rated' => 'Top Rated',
        'rising-talent' => 'Rising Talent',
        'verified-pro' => 'Verified Pro',
        'quest-champion' => 'Quest Champion',
    ],

    'review_integrity_patterns' => [
        'rating_spike' => 'Suspicious rating spike',
        'polarized_reviewer' => 'Polarized reviewer pattern',
        'reciprocal_reviews' => 'Reciprocal review exchange',
        'timing_cluster' => 'Review timing cluster',
    ],
];
