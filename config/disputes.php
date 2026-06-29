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
        'transparent' => 'Both parties see the same case summary, messages, settlement offers, and high-level status updates.',
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

    'platform_resolution_fee_percent' => 0,

    'max_appeals_per_dispute' => 1,

    'account_review_after_dispute_count' => 3,

    'account_suspension_review_after_lost_disputes' => 3,

    'self_resolution_response_hours' => 48,

    'formal_no_response_ruling_hours' => 72,

    'silence_comms_min_days' => 5,

    'mutual_resolve_reminder_hours' => 24,

    'negotiation' => [
        'max_attempts_per_party' => 2,
        'response_hours' => 24,
        'mutual_approval_appeal_days' => 4,
        'enforcement_rejection_hours' => 48,
        'appeal_window_hours' => 48,
        'appeal_response_hours' => 24,
    ],

    'intake' => [
        'description_min_words' => 150,
        'description_max_words' => 1000,
        'evidence_max_files' => 10,
        'evidence_max_file_kb' => 51200,
        'external_links_max' => 10,
    ],

    'management' => [
        'max_reassignments' => 2,
        'staff_overload_threshold' => 15,
        'appeal_window_days' => 7,
        'critical_amount_minor' => 1_000_000_00,
        'high_amount_minor' => 500_000_00,
        'medium_amount_minor' => 100_000_00,
        'evidence_request_templates' => [
            ['key' => 'screenshots', 'label' => 'Screenshots of deliverable', 'body' => 'Please upload clear screenshots showing the issue you reported, including device/browser if relevant.'],
            ['key' => 'source_files', 'label' => 'Source files or exports', 'body' => 'Please upload the latest source files, exports, or deliverables so we can compare against the contract scope.'],
            ['key' => 'communication', 'label' => 'Communication history', 'body' => 'Please summarise any off-platform communication related to this dispute, or confirm all relevant messages are on HustleSafe.'],
            ['key' => 'timeline', 'label' => 'Timeline clarification', 'body' => 'Please provide dates and times for key events (submissions, revision requests, and responses).'],
        ],
        'sanction_options' => [
            ['value' => 'none', 'label' => 'None'],
            ['value' => 'warn_freelancer', 'label' => 'Formal warning — freelancer'],
            ['value' => 'warn_client', 'label' => 'Formal warning — client'],
            ['value' => 'suspend_7', 'label' => 'Temporary suspension — 7 days'],
            ['value' => 'suspend_30', 'label' => 'Temporary suspension — 30 days'],
            ['value' => 'permanent_ban', 'label' => 'Permanent ban'],
            ['value' => 'tier_demotion', 'label' => 'Verification tier demotion'],
            ['value' => 'category_ban', 'label' => 'Category ban'],
        ],
        'outcome_action_options' => [
            ['value' => 'standard_payout', 'label' => 'Split or award payment'],
            ['value' => 'force_revision', 'label' => 'Give another chance to fix'],
            ['value' => 'extend_deadline', 'label' => 'More time to finish'],
            ['value' => 'refund_cancel', 'label' => 'Refund client and cancel job'],
            ['value' => 'terminate_contract', 'label' => 'End contract without refund'],
            ['value' => 'mediation', 'label' => 'Schedule mediation call'],
        ],
    ],
];
