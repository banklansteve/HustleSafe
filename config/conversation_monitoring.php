<?php

return [
    'health_score' => [
        'default' => 100,
        'risk_queue_threshold' => (int) env('CONVERSATION_HEALTH_RISK_THRESHOLD', 45),
    ],

    'penalties' => [
        'off_platform_payment' => 22,
        'external_contact' => 18,
        'abusive_language' => 15,
        'blacklisted_keyword' => 8,
        'cross_party_multiplier' => 1.35,
        'repeat_same_party_multiplier' => 0.85,
    ],

    'systematic' => [
        'window_days' => 30,
        'min_distinct_counterparties' => 3,
        'min_instances' => 3,
        'categories' => ['off_platform_payment', 'external_contact'],
    ],

    'fuzzy' => [
        'max_levenshtein' => 2,
        'min_token_length' => 4,
    ],

    'off_platform_phrases' => [
        'pay me directly',
        'pay directly',
        'pay outside',
        'pay off platform',
        'outside the platform',
        'off platform',
        'send it to my account',
        'send to my account',
        'my account number',
        'dont use the platform',
        "don't use the platform",
        'do not use the platform',
        'let me pay you outside',
    ],

    'contact_phrases' => [
        'whatsapp me',
        'ping me on whatsapp',
        'on whatsapp',
        'reach me on whatsapp',
        'on telegram',
        'telegram me',
        'dm me on',
        'add me on snap',
        'on snapchat',
        'on instagram',
        'on twitter',
        'on x.com',
    ],
];
