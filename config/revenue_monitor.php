<?php

return [
    'processor_fee_percent' => [
        'premium' => (float) env('REVENUE_MONITOR_PREMIUM_FEE_PERCENT', 5),
        'quest_boost' => (float) env('REVENUE_MONITOR_BOOST_FEE_PERCENT', 7),
        'platform_fee' => 0,
    ],
    'refund_statuses' => ['refunded', 'reversed', 'chargeback'],
];
