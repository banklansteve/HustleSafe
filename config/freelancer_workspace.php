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
    | High-value quests (minor units, kobo)
    |--------------------------------------------------------------------------
    |
    | Document ID can be approved while a live selfie+ID check is still pending.
    | Quests at or above this budget (or quotes at/above it) require an approved
    | "live presence" submission. Default ≈ ₦200,000.
    |
    */
    'high_value_quest_budget_minor' => (int) env('FREELANCER_HIGH_VALUE_QUEST_BUDGET_MINOR', 80_000_000),

    /*
    |--------------------------------------------------------------------------
    | Minimum trust profile completion (%) before sending proposals
    |--------------------------------------------------------------------------
    */
    'min_profile_completion_for_proposals' => (int) env('FREELANCER_MIN_PROFILE_PERCENT', 55),

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
