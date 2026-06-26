<?php

return [
    'delivery_statuses' => [
        ['value' => 'pending', 'label' => 'Pending delivery'],
        ['value' => 'submitted', 'label' => 'Submitted — awaiting review'],
        ['value' => 'revision', 'label' => 'Revision requested'],
        ['value' => 'approved', 'label' => 'Approved'],
    ],

    'payment_statuses' => [
        ['value' => 'unfunded', 'label' => 'Unfunded'],
        ['value' => 'awaiting_funding', 'label' => 'Awaiting funding'],
        ['value' => 'funded', 'label' => 'Funded / holding'],
        ['value' => 'partially_released', 'label' => 'Partially released'],
        ['value' => 'released', 'label' => 'Released'],
        ['value' => 'refunded', 'label' => 'Refunded'],
    ],

    'dispute_statuses' => [
        ['value' => 'none', 'label' => 'No dispute'],
        ['value' => 'active', 'label' => 'Active dispute'],
        ['value' => 'resolved', 'label' => 'Resolved'],
    ],

    'risk_levels' => [
        ['value' => 'low', 'label' => 'Low'],
        ['value' => 'medium', 'label' => 'Medium'],
        ['value' => 'high', 'label' => 'High'],
        ['value' => 'critical', 'label' => 'Critical'],
    ],

    'sort_options' => [
        ['value' => 'recent', 'label' => 'Most recent'],
        ['value' => 'oldest', 'label' => 'Oldest first'],
        ['value' => 'highest_value', 'label' => 'Highest value'],
        ['value' => 'lowest_value', 'label' => 'Lowest value'],
        ['value' => 'due_soon', 'label' => 'Due soon'],
        ['value' => 'overdue', 'label' => 'Overdue'],
        ['value' => 'highest_risk', 'label' => 'Highest risk'],
    ],

    'alert_types' => [
        ['value' => 'overdue', 'label' => 'Overdue'],
        ['value' => 'disputed', 'label' => 'Disputed'],
        ['value' => 'flagged', 'label' => 'Flagged for review'],
        ['value' => 'pending_escrow', 'label' => 'Pending escrow'],
        ['value' => 'amendment_pending', 'label' => 'Amendment pending'],
        ['value' => 'delivery_review', 'label' => 'Delivery awaiting review'],
        ['value' => 'patrol', 'label' => 'Patrol flag'],
    ],

    'patrol' => [
        'pending_escrow_stale_hours' => 72,
        'delivery_review_stale_hours' => 48,
        'overdue_delivery_medium_hours' => (int) env('CONTRACT_PATROL_OVERDUE_MEDIUM_HOURS', 24),
        'overdue_delivery_critical_hours' => (int) env('CONTRACT_PATROL_OVERDUE_CRITICAL_HOURS', 72),
        'freelancer_inactive_after_award_hours' => (int) env('CONTRACT_PATROL_INACTIVE_AFTER_AWARD_HOURS', 48),
    ],

    'quality_audit' => [
        'default_sample_size' => 50,
    ],

    'settings' => [
        'auto_release_hours' => ['label' => 'Auto-release delay (hours)', 'default' => 72, 'min' => 1, 'max' => 720],
        'max_revision_requests' => ['label' => 'Max revision requests', 'default' => 5, 'min' => 1, 'max' => 20],
        'dispute_evidence_deadline_hours' => ['label' => 'Dispute evidence deadline (hours)', 'default' => 24, 'min' => 1, 'max' => 168],
        'dispute_resolution_days' => ['label' => 'Dispute resolution deadline (days)', 'default' => 14, 'min' => 1, 'max' => 60],
        'critical_risk_threshold' => ['label' => 'Critical risk score threshold', 'default' => 75, 'min' => 50, 'max' => 100],
        'high_risk_threshold' => ['label' => 'High risk score threshold', 'default' => 50, 'min' => 25, 'max' => 90],
    ],
];
