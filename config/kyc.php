<?php

return [
    'tiers' => [
        0 => 'Unverified',
        1 => 'Email and phone verified',
        2 => 'Identity verified',
        3 => 'Address verified',
        4 => 'Fully verified',
        5 => 'Business verified',
    ],

    'feature_gates' => [
        'browse' => 0,
        'submit_proposal' => 1,
        'post_quest' => 1,
        'high_value_quest' => 2,
        'withdraw_large_amount' => 4,
        'business_badge' => 5,
    ],

    'limits' => [
        'tier_1_client_quest_minor' => 25_000_000,
        'tier_2_client_quest_minor' => 100_000_000,
        'tier_4_single_withdrawal_minor' => 500_000_000,
    ],

    'thresholds' => [
        'nin' => 85,
        'bvn' => 85,
        'face_similarity' => 85,
    ],

    'resubmission_limit' => 3,
];
