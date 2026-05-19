<?php

return [
    'levels' => [
        0 => ['label' => 'L0 - Unverified', 'requirements' => []],
        1 => ['label' => 'L1 - Basic', 'requirements' => ['email']],
        2 => ['label' => 'L2 - Identified', 'requirements' => ['email', 'nin', 'identity_address']],
        3 => ['label' => 'L3 - Trusted', 'requirements' => ['email', 'nin', 'identity_address', 'bvn']],
        4 => ['label' => 'L4 - Verified Business', 'requirements' => ['email', 'nin', 'identity_address', 'bvn', ['any_of' => ['cac', 'tin']]]],
        5 => ['label' => 'L5 - Fully Verified', 'requirements' => ['email', 'nin', 'identity_address', 'bvn', ['any_of' => ['cac', 'tin']], ['account_age_days' => 180]]],
    ],
    'types' => [
        'email' => ['label' => 'Email Verification', 'enabled' => true, 'manual_review' => false],
        'identity_address' => ['label' => 'Identity & Address Verification', 'enabled' => true, 'manual_review' => true],
        'nin' => ['label' => 'NIN Verification', 'enabled' => true, 'manual_review' => true],
        'bvn' => ['label' => 'BVN Verification', 'enabled' => true, 'manual_review' => true, 'sensitive' => true],
        'cac' => ['label' => 'CAC Verification', 'enabled' => true, 'manual_review' => true],
        'tin' => ['label' => 'TIN Verification', 'enabled' => true, 'manual_review' => true],
        'professional_certificate' => ['label' => 'Professional Certificate Verification', 'enabled' => true, 'manual_review' => true],
        'portfolio_review' => ['label' => 'Portfolio Review Verification', 'enabled' => true, 'manual_review' => true, 'soft' => true],
    ],
    'limits' => [
        'client_posting_minor' => [0 => 0, 1 => 5_000_000, 2 => 50_000_000, 3 => 200_000_000, 4 => 500_000_000, 5 => 1_000_000_000],
        'freelancer_proposal_minor' => [0 => 0, 1 => 5_000_000, 2 => 50_000_000, 3 => 200_000_000, 4 => 500_000_000, 5 => 1_000_000_000],
    ],
    'safeguards' => [
        'escrow_enforcement_threshold_minor' => 100,
        'milestone_enforcement_threshold_minor' => 100_000_000,
        'minimum_milestone_count' => 2,
        'new_account_cooldown_days' => 30,
        'quest_repost_limit' => 2,
        'high_value_arbitration_threshold_minor' => 100_000_000,
        'anomaly_new_account_days' => 7,
        'anomaly_near_ceiling_percent' => 90,
        'anomaly_verification_window_hours' => 24,
        'anomaly_high_value_minor' => 10_000_000,
        'anomaly_proposal_burst_count' => 5,
        'anomaly_proposal_burst_minutes' => 60,
        'rapid_completion_high_value_minor' => 10_000_000,
    ],
];
