<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Offer limits before identity verification is approved
    |--------------------------------------------------------------------------
    |
    | Freelancers with a complete address + at least one subcategory can send
    | a small number of offers on modest budgets until government ID is approved.
    |
    */
    'limited_offer_max_count' => (int) env('FREELANCER_LIMITED_OFFER_MAX', 3),

    'limited_offer_max_budget_minor' => (int) env('FREELANCER_LIMITED_OFFER_MAX_BUDGET_MINOR', 2_000_000),

    /*
    |--------------------------------------------------------------------------
    | Reminder cadence (hours between nudges)
    |--------------------------------------------------------------------------
    */
    'setup_reminder_interval_hours' => (int) env('FREELANCER_SETUP_REMINDER_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Withdrawal readiness (used by payout flows when implemented)
    |--------------------------------------------------------------------------
    |
    | We require approved identity + structured address before funds leave escrow.
    |
    */
    'withdrawal_requires_identity' => filter_var(env('FREELANCER_WITHDRAWAL_REQUIRES_IDENTITY', true), FILTER_VALIDATE_BOOL),
];
