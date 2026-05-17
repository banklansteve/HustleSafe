<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core philosophy (reference for UI + ops copy)
    |--------------------------------------------------------------------------
    */
    'philosophy' => [
        'evidence_first' => 'Decisions are anchored to dated uploads, links, and checklist answers — not tone or private side channels.',
        'time_boxed' => 'Each stage carries a visible countdown. Silence advances the case so good-faith actors are not held hostage.',
        'transparent' => 'Both parties always see the same thread, offers, and audit trail entries.',
        'escalatable' => 'Missed deadlines automatically escalate tiers so nothing stalls indefinitely.',
        'auditable' => 'Every state change is written once to the immutable dispute_events stream.',
    ],

    /*
    |--------------------------------------------------------------------------
    | Business rules (tune before launch)
    |--------------------------------------------------------------------------
    |
    | Amounts use the same minor units as quests (see budget_amount_minor).
    |
    */
    'minimum_disputed_amount_minor' => 500_000,

    'max_days_after_completion_to_open' => 14,

    'platform_resolution_fee_percent' => 2.0,

    'max_appeals_per_dispute' => 1,

    'account_suspension_review_after_lost_disputes' => 3,

    'self_resolution_response_hours' => 48,

    'formal_no_response_ruling_hours' => 72,

    'silence_comms_min_days' => 5,

    'mutual_resolve_reminder_hours' => 24,
];
