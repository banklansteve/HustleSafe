<?php

return [
    'client_levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN verified', 'requirements' => ['email', 'identity_address', 'nin']],
        4 => ['label' => 'L4 — BVN verified', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        5 => ['label' => 'L5 — Established account', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['account_age_days' => 180]]],
    ],
    'freelancer_levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN verified', 'requirements' => ['email', 'identity_address', 'nin']],
        4 => ['label' => 'L4 — BVN verified', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        5 => ['label' => 'L5 — CAC/TIN verification', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['any_of' => ['cac', 'tin']]]],
        6 => ['label' => 'L6 — Selfie + ID + account age', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['any_of' => ['cac', 'tin']], 'live_presence', ['account_age_days' => 90]]],
    ],
    /** @deprecated Use client_levels — kept for backward compatibility */
    'levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN verified', 'requirements' => ['email', 'identity_address', 'nin']],
        4 => ['label' => 'L4 — BVN verified', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        5 => ['label' => 'L5 — Established account', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['account_age_days' => 180]]],
    ],
    'stage_content' => [
        'client' => [
            1 => [
                'title' => 'Verify your email',
                'message' => 'Check your inbox for the verification link. Request a new link if needed.',
                'info_bar' => 'Verify your email to reach L1 and start building your verification level.',
            ],
            2 => [
                'title' => 'Identity & address verification',
                'message' => 'Upload a government photo ID and proof of address (utility bill, bank statement, or tenancy within the last 3 months).',
                'info_bar' => 'Complete identity and address verification to unlock L2.',
            ],
            3 => [
                'title' => 'NIN verification',
                'message' => 'Enter your 11-digit National Identification Number for review.',
                'info_bar' => 'Submit your NIN to unlock L3.',
            ],
            4 => [
                'title' => 'BVN verification',
                'message' => 'Enter your 11-digit Bank Verification Number for review.',
                'info_bar' => 'Submit your BVN to unlock L4.',
            ],
            5 => [
                'title' => 'Established account',
                'message' => 'Your account must be at least {account_age_days} days old for the L5 posting limit to apply.',
                'info_bar' => 'After L4 checks are approved you reach L5. The L5 posting limit unlocks once your account reaches {account_age_days} days on HustleSafe.',
            ],
        ],
        'freelancer' => [
            1 => [
                'title' => 'Verify your email',
                'message' => 'Check your inbox for the verification link. Request a new link if needed.',
                'info_bar' => 'Verify your email to reach L1.',
            ],
            2 => [
                'title' => 'Identity & address verification',
                'message' => 'Upload a government photo ID and proof of address for review.',
                'info_bar' => 'Complete identity and address verification to unlock L2.',
            ],
            3 => [
                'title' => 'NIN verification',
                'message' => 'Enter your 11-digit National Identification Number for review.',
                'info_bar' => 'Submit your NIN to unlock L3 and raise your proposal limit.',
            ],
            4 => [
                'title' => 'BVN verification',
                'message' => 'Enter your 11-digit Bank Verification Number for review.',
                'info_bar' => 'Submit your BVN to unlock L4 and raise your proposal limit.',
            ],
            5 => [
                'title' => 'CAC or TIN verification',
                'message' => 'Submit your RC number (CAC) or TIN. You only need one of these for L5.',
                'info_bar' => 'Complete business verification (CAC or TIN) to unlock L5.',
            ],
            6 => [
                'title' => 'Selfie + ID (L6)',
                'message' => 'Upload a selfie holding your government ID beside your face. The L6 proposal limit also requires at least {account_age_days} days account age.',
                'info_bar' => 'Reach L6 with an approved selfie + ID. The highest proposal limit unlocks after {account_age_days} days on HustleSafe.',
            ],
        ],
    ],
    'types' => [
        'email' => ['label' => 'Email Verification', 'enabled' => true, 'manual_review' => false],
        'identity_address' => ['label' => 'Identity & Address Verification', 'enabled' => true, 'manual_review' => true],
        'nin' => ['label' => 'NIN Verification', 'enabled' => true, 'manual_review' => true],
        'bvn' => ['label' => 'BVN Verification', 'enabled' => true, 'manual_review' => true, 'sensitive' => true],
        'cac' => ['label' => 'CAC Verification', 'enabled' => true, 'manual_review' => true, 'freelancer_only' => true],
        'tin' => ['label' => 'TIN Verification', 'enabled' => true, 'manual_review' => true, 'freelancer_only' => true],
        'professional_certificate' => ['label' => 'Professional Certificate / Membership', 'enabled' => true, 'manual_review' => true, 'freelancer_only' => true, 'optional' => true],
        'live_presence' => ['label' => 'Selfie + ID (high-value quest unlock)', 'enabled' => true, 'manual_review' => true, 'freelancer_only' => true],
        'portfolio_review' => ['label' => 'Portfolio Review Verification', 'enabled' => false, 'manual_review' => true, 'soft' => true],
    ],
    /**
     * Final-tier manual reviews reserved for Super Admin (staff admins handle earlier steps).
     */
    'staff_review' => [
        'client_super_admin_only_types' => ['bvn'],
        'freelancer_super_admin_only_types' => ['live_presence'],
    ],
    'limits' => [
        'client_posting_minor' => [0 => 0, 1 => 5_000_000, 2 => 50_000_000, 3 => 200_000_000, 4 => 100_000_000, 5 => 1_000_000_000],
        'freelancer_proposal_minor' => [0 => 0, 1 => 5_000_000, 2 => 50_000_000, 3 => 100_000_000, 4 => 200_000_000, 5 => 500_000_000, 6 => 1_000_000_000],
        'freelancer_monthly_proposals' => [0 => 0, 1 => 3, 2 => 5, 3 => 8, 4 => 12, 5 => 16, 6 => 25],
    ],
    'safeguards' => [
        'escrow_enforcement_threshold_minor' => 100,
        'milestone_enforcement_threshold_minor' => 100_000_000,
        'minimum_milestone_count' => 2,
        'quest_repost_limit' => 2,
        'high_value_arbitration_threshold_minor' => 100_000_000,
        'anomaly_new_account_days' => 7,
        'anomaly_near_ceiling_percent' => 90,
        'anomaly_verification_window_hours' => 24,
        'anomaly_high_value_minor' => 10_000_000,
        'anomaly_proposal_burst_count' => 5,
        'anomaly_proposal_burst_minutes' => 60,
        'rapid_completion_high_value_minor' => 10_000_000,
        'min_quest_budget_minor' => 10_000,
    ],
];
