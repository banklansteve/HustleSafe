<?php

/**
 * Categories where clients may choose recurring / installment payment (e.g. tutoring over months).
 * Matched on parent category slug.
 */
return [
    'eligible_parent_slugs' => [
        'education-training',
        'childcare-eldercare',
    ],

    /** First pay period length in days after contract start (before first payout cycle). */
    'first_period_days' => 7,

    'contract_duration_options' => [
        3 => '3 months',
        6 => '6 months',
        12 => '12 months',
    ],

    'frequencies' => [
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    /** Days before contract end when clients may extend, continue, or republish. */
    'renewal_window_days' => 14,
];
