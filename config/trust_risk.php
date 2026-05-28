<?php

return [
    'weights' => [
        'kyc' => 0.20,
        'account_activity' => 0.15,
        'disputes' => 0.15,
        'flagged_conversations' => 0.10,
        'review_authenticity' => 0.10,
        'payment_behaviour' => 0.15,
        'device_ip' => 0.10,
        'velocity' => 0.05,
    ],

    'tier_thresholds' => [
        'low_max' => 39,
        'medium_max' => 69,
        'high_max' => 84,
    ],

    'monitoring_queue_min_score' => 40,

    'score_change_feed_threshold' => 10,

    'device_ip' => [
        'distinct_devices_flag' => 4,
        'lookback_days' => 30,
    ],

    'velocity' => [
        'lookback_days' => 30,
        'spike_multiplier' => 2.5,
    ],

    'account_activity' => [
        'tenure_floor_days' => 3,
        'contracts_anomaly_threshold' => 5,
    ],
];
