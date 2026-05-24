<?php

return [
    'client_levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN verified', 'requirements' => ['email', 'identity_address', 'nin']],
        4 => ['label' => 'L4 — BVN verified', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        5 => ['label' => 'L5 — Established account (180 days)', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['account_age_days' => 180]]],
    ],
    'freelancer_levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN & BVN', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        4 => ['label' => 'L4 — CAC/TIN verification', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['any_of' => ['cac', 'tin']]]],
        5 => ['label' => 'L5 — 90 days + Selfie + ID', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['any_of' => ['cac', 'tin']], 'live_presence', ['account_age_days' => 90]]],
    ],
    /** @deprecated Use client_levels — kept for backward compatibility */
    'levels' => [
        0 => ['label' => 'L0 — No checks', 'requirements' => []],
        1 => ['label' => 'L1 — Email verified', 'requirements' => ['email']],
        2 => ['label' => 'L2 — Identity & address', 'requirements' => ['email', 'identity_address']],
        3 => ['label' => 'L3 — NIN verified', 'requirements' => ['email', 'identity_address', 'nin']],
        4 => ['label' => 'L4 — BVN verified', 'requirements' => ['email', 'identity_address', 'nin', 'bvn']],
        5 => ['label' => 'L5 — Established account (180 days)', 'requirements' => ['email', 'identity_address', 'nin', 'bvn', ['account_age_days' => 180]]],
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
                'message' => 'Your account must be at least 180 days old to reach L5.',
                'info_bar' => 'After L4, L5 unlocks automatically once your account reaches 180 days on HustleSafe.',
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
                'title' => 'NIN & BVN verification',
                'message' => 'Submit both your NIN and BVN. Both are required to unlock L3.',
                'info_bar' => 'Add your NIN and BVN to unlock L3 and raise your proposal limit.',
            ],
            4 => [
                'title' => 'CAC or TIN verification',
                'message' => 'Submit your RC number (CAC) or TIN. You only need one of these for L4.',
                'info_bar' => 'Complete business verification (CAC or TIN) to unlock L4.',
            ],
            5 => [
                'title' => 'Selfie + ID (L5)',
                'message' => 'Your account must be at least 90 days old. Upload a selfie holding your government ID beside your face.',
                'info_bar' => 'Reach L5 with 90 days account age plus an approved selfie + ID for high-value quests.',
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
