<?php

return [
    'quality' => [
        'min_words_full_weight' => 30,
        'brief_threshold' => 40,
        'brief_rating_weight' => 0.4,
        'filler_phrases' => [
            'great job',
            'highly recommend',
            '5 stars',
            'five stars',
            'good work',
            'nice work',
            'would recommend',
        ],
        'work_language_hints' => [
            'deliverable',
            'timeline',
            'communication',
            'revision',
            'milestone',
            'deadline',
            'responsive',
            'quality',
            'scope',
            'brief',
            'project',
        ],
    ],

    'authenticity' => [
        'velocity' => [
            'window_hours' => 24,
            'min_five_star' => 3,
            'young_account_days' => 30,
            'min_contracts_trusted' => 3,
            'min_young_reviewers' => 2,
        ],
        'sentiment' => [
            'positive_threshold' => 0.6,
            'negative_threshold' => 0.3,
        ],
        'reciprocal' => [
            'window_hours' => 6,
            'contract_close_days' => 7,
            'max_star_delta' => 1,
        ],
        'ip_cluster' => [
            'window_hours' => 72,
            'min_reviews' => 3,
        ],
    ],

    'amendment' => [
        'hours_to_respond' => 48,
        'default_actions' => [
            'velocity_cluster' => 'auto_remove',
            'sentiment_mismatch' => 'auto_publish',
            'reciprocal_pair' => 'auto_remove',
            'ip_cluster' => 'auto_remove',
            'blacklisted_keyword' => 'auto_remove',
        ],
    ],

    'manipulation' => [
        'freelancer_young_account_days' => 45,
        'freelancer_min_prior_contracts' => 3,
        'client_low_rating_window_days' => 60,
        'client_min_distinct_freelancers' => 4,
    ],
];
