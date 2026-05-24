<?php

return [
    'invalid' => [
        'label' => 'Invalid document',
        'hint' => 'The file is not an acceptable government ID or proof-of-address type.',
    ],
    'incorrect' => [
        'label' => 'Incorrect information',
        'hint' => 'Name, number, or address does not match what you entered.',
    ],
    'expired' => [
        'label' => 'Expired document',
        'hint' => 'The ID or bill is outside the allowed validity period.',
    ],
    'illegible' => [
        'label' => 'Illegible or poor quality',
        'hint' => 'Text or photo is blurry, cropped, or too dark to review.',
    ],
    'mismatch' => [
        'label' => 'Details mismatch',
        'hint' => 'Document details do not match your account profile.',
    ],
    'incomplete' => [
        'label' => 'Incomplete submission',
        'hint' => 'Required pages, files, or fields were missing.',
    ],
    'duplicate' => [
        'label' => 'Duplicate submission',
        'hint' => 'This document or number is already linked to another account.',
    ],
    'not_recent' => [
        'label' => 'Not recent enough',
        'hint' => 'Proof-of-address must be within the last 3 months.',
    ],
    'fraud_suspected' => [
        'label' => 'Suspected fraudulent document',
        'hint' => 'Document appears altered, forged, or tampered with.',
    ],
    'other' => [
        'label' => 'Other (explain below)',
        'hint' => 'Add a short note so the user knows what to fix.',
    ],
];
