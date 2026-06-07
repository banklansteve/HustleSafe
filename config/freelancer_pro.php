<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Premium is orthogonal to trust / verification tiers
    |--------------------------------------------------------------------------
    |
    | Pro unlocks convenience and visibility — it never bypasses verification
    | level, account age, or per-tier job value limits.
    |
    */

    'match_score_bonus_points' => 8,

    'kyc_sla_hours_standard' => 72,
    'kyc_sla_hours_pro' => 24,

    'free_portfolio_item_limit_default' => 5,

    'pro_profile_sections' => [
        'testimonials' => ['max_items' => 6],
        'external_links' => ['max_items' => 8],
        'media_links' => ['max_items' => 12],
    ],

];
