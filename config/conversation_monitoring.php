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

    'scoring' => [
        'flag_threshold' => (int) env('CONVERSATION_FLAG_THRESHOLD', 85),
        'review_threshold' => (int) env('CONVERSATION_REVIEW_THRESHOLD', 70),
        'threshold_adjustment_per_dismissal_rate' => 2,
        'max_threshold_adjustment' => 5,
    ],

    'fuzzy' => [
        'max_levenshtein' => 2,
        'platform_max_levenshtein' => 3,
        'min_token_length' => 4,
    ],

    'payment' => [
        'action_verbs' => [
            'send', 'pay', 'transfer', 'deposit', 'credit', 'wire', 'remit', 'withdraw',
            'trf', 'trow', 'settle', 'sort out', 'sort me', 'hook me up', 'get back to',
            'pya', 'pye', 'pment', 'trnsf', 'trnsfr', 'crdt', 'wir', 'cash out',
        ],
        'payment_providers' => [
            'opay', 'palmpay', 'kuda', 'moniepoint', 'moniept', 'paga', 'fairmoney', 'carbon', 'branch',
            'paystack', 'flutterwave', 'momo', 'mtn mo', 'bank app',
        ],
        'canonical_payment_providers' => [
            'opay' => ['opay'],
            'palmpay' => ['palmpay', 'palm pay'],
            'kuda' => ['kuda'],
            'moniepoint' => ['moniepoint', 'moniept', 'monie pt'],
            'paystack' => ['paystack', 'pay stack'],
            'flutterwave' => ['flutterwave', 'flutter wave'],
            'paga' => ['paga'],
            'fairmoney' => ['fairmoney', 'fair money'],
        ],
        'pay_via_phrases' => [
            'pay me on', 'pay you on', 'pay me via', 'pay you via', 'pay on', 'pay via',
            'send to my', 'transfer to my',
        ],
        'method_references' => [
            'account', 'bank', 'nuban', 'account number', 'acct', 'bank acc', 'my account',
            'direct', 'bank transfer', 'wire transfer', 'swift code', 'mobile money', 'momo',
            'mtn', 'airtel', 'glo', '9mobile', 'cash', 'hand over', 'collect', 'aza', 'azza', 'alert',
            'acc', 'a/c', 'bnk', 'naira', 'n41ra',
        ],
        'bypass_phrases' => [
            'dont use platform', "don't use platform", 'do not use platform', 'off platform',
            'outside the platform', 'pay outside', 'pay directly', 'pay me directly',
            'without escrow', 'skip escrow', 'bypass escrow',
        ],
        'location_dampeners' => [
            'state', 'lga', 'area', 'from', 'live in', 'based in', 'located', 'region', 'city', 'town',
        ],
        'weights' => [
            'action_verb' => 30,
            'second_action_verb' => 10,
            'payment_method' => 20,
            'account_reference' => 15,
            'number_pattern' => 20,
            'complex_number_pattern' => 25,
            'nigerian_slang' => 15,
            'explicit_bypass' => 20,
            'nuban' => 25,
            'payment_url' => 30,
            'payment_provider' => 25,
            'provider_phone_combo' => 30,
            'pay_via_provider' => 20,
            'obfuscated_payment_provider' => 40,
            'multi_payment_escalation' => 45,
            'location_dampener' => -15,
        ],
    ],

    'contact' => [
        'platforms' => [
            'whatsapp', 'whazzap', 'whats app', 'telegram', 'signal', 'viber', 'wechat',
            'facebook', 'instagram', 'twitter', 'tiktok', 'snapchat', 'snap', 'messenger',
            'wp', 'tg', 'fb', 'ig', 'tw', 'tt', 'insta', 'instg', 'fbk', 'teleg', 'tgm',
        ],
        'platform_aliases' => [
            'whtsapp', 'whatsap', 'watsapp', 'whats', 'wtsapp', 'telgm', 'telegm', 'telegrm', 'tgram', 'telegran',
            'instag', 'insta', 'moniept',
        ],
        'short_platform_tokens' => ['fb', 'ig', 'wp', 'tg', 'tw', 'tt', 'wa', 'snap'],
        'canonical_platforms' => [
            'whatsapp' => ['whatsapp', 'whazzap', 'whats app', 'whtsapp', 'wtsapp', 'whatsap', 'watsapp', 'wp'],
            'telegram' => ['telegram', 'teleg', 'telgm', 'telegm', 'telegrm', 'tgram', 'telegran', 'tg', 'tgm'],
            'instagram' => ['instagram', 'instag', 'insta', 'instg', 'ig'],
            'facebook' => ['facebook', 'fbk', 'fb'],
            'messenger' => ['messenger', 'messenger'],
            'signal' => ['signal'],
            'tiktok' => ['tiktok', 'tt'],
            'twitter' => ['twitter', 'tw'],
            'snapchat' => ['snapchat', 'snap'],
        ],
        'benign_platform_context_patterns' => [
            'in the bio', 'in my bio', 'on my profile', 'my portfolio', 'website link', 'see my profile',
        ],
        'fuzzy_platforms' => ['whatsapp', 'telegram', 'facebook', 'instagram', 'signal', 'messenger'],
        'reach_phrases' => [
            'reaching you via', 'reach you via', 'reaching me via', 'reach me via',
            'contact you via', 'contact me via', 'get to you via', 'getting to you via',
        ],
        'cue_tokens' => ['dm', 'dms', 'pm', 'slide', 'shoot', 'inbox', 'msg'],
        'action_verbs' => [
            'contact', 'message', 'call', 'text', 'reach', 'add', 'send', 'hit me up',
            'hit me', 'buzz me', 'link me', 'holler at me', 'drop me a line',
            'txt', 'cntct', 'reach me', 'contact me', 'message me', 'find me', 'ping me',
        ],
        'bypass_phrases' => [
            'talk off', 'off here', 'off platform', 'off app', 'leave here', 'chat outside',
            'outside the platform', 'outside here', 'not on here', 'away from here',
            'move off here', 'continue off here',
        ],
        'weights' => [
            'platform' => 25,
            'contact_action' => 25,
            'phone_number' => 20,
            'email' => 15,
            'multiple_platforms' => 10,
            'handle_disclosure' => 15,
            'explicit_bypass' => 35,
            'contact_cue' => 15,
            'multiple_contact_cues' => 15,
            'adjacent_slide_dm' => 20,
            'hit_me_contact' => 20,
            'mixed_word_figure' => 20,
            'platform_phone_combo' => 30,
            'reach_via' => 30,
            'slide_me_msg' => 25,
            'canonical_platform' => 30,
            'obfuscated_platform_token' => 40,
            'multi_platform_escalation' => 45,
            'benign_platform_dampener' => -35,
        ],
    ],

    'scan_queue_connection' => env('CONVERSATION_SCAN_QUEUE_CONNECTION', 'sync'),

    'history' => [
        'retention_months' => (int) env('CONVERSATION_REVIEW_RETENTION_MONTHS', 24),
    ],

    'abuse' => [
        'insults' => [
            'stupid', 'idiot', 'useless', 'incompetent', 'lazy', 'dishonest', 'fraud', 'scammer',
            'liar', 'fool', 'mumu', 'foolish', 'wayward', 'trado', 'con artist', 'fraudster',
        ],
        'threats' => [
            'beat you', 'sue you', 'expose you', 'report you', 'you better', 'watch out',
            'come for you', 'ruin you', 'just wait', 'in trouble', 'settle you', 'you will hear',
            'deal with you', 'i will kill', 'kill you',
        ],
        'discriminatory' => [
            'you people', 'your tribe', 'your kind', 'your mentality', 'like a woman', 'like a man',
            'female logic', 'male logic',
        ],
        'self_directed_markers' => ["i'm", 'im', 'i am', 'myself', 'my fault', 'my mistake'],
        'system_criticism' => ['this system', 'the system', 'this platform', 'the platform', 'this app'],
        'work_criticism' => [
            'poor quality', 'does not match', "doesn't match", 'not match', 'work quality',
            'the design', 'the deliverable', 'the proposal', 'the brief', 'the scope',
        ],
        'weights' => [
            'directed_insult' => 30,
            'threat' => 45,
            'discriminatory' => 60,
            'all_caps' => 10,
            'work_criticism_dampener' => -20,
            'self_directed_dampener' => -25,
            'system_criticism_dampener' => -20,
        ],
        'auto_flag_discriminatory_score' => 99,
        'threat_flag_score' => 90,
    ],

    'spoken_digit_words' => [
        'zero', 'oh', 'o', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine',
    ],
];
