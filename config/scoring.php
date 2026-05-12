<?php

/**
 * Trust & reputation scoring — tweak weights anytime; keep sums at 1.0 per persona.
 * Persisted scores live in user_trust_metrics; services recalculate from behaviour + verification.
 */
return [

    'freelancer' => [
        'weights' => [
            'average_rating' => 0.22,
            'profile_completion' => 0.09,
            'account_age' => 0.07,
            'dispute_inverse' => 0.13,
            'on_time_delivery' => 0.11,
            'email_verified' => 0.05,
            'identity_verified' => 0.08,
            'address_verified' => 0.08,
            'qualifications_verified' => 0.09,
            'cac_verified' => 0.08,
        ],
        'account_age_cap_days' => 730,
        'neutral_dispute_component' => 0.85,
        'neutral_on_time_component' => 0.85,
        /** When no public credentials listed yet */
        'neutral_qualifications_norm' => 0.55,
        /** When no CAC number on file */
        'neutral_cac_norm' => 0.5,
    ],

    'client' => [
        'weights' => [
            'average_rating' => 0.30,
            'profile_completion' => 0.14,
            'account_age' => 0.10,
            'dispute_inverse' => 0.14,
            'smooth_closure' => 0.13,
            'email_verified' => 0.07,
            'identity_verified' => 0.07,
            'address_verified' => 0.05,
        ],
        'account_age_cap_days' => 730,
        'neutral_dispute_component' => 0.88,
        'neutral_smooth_closure' => 0.88,
    ],

    'reviews' => [
        'edit_window_hours' => (int) env('REVIEW_EDIT_WINDOW_HOURS', 72),
    ],

];
