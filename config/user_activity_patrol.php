<?php

return [
    'dispute_spike_count' => 3,
    'dispute_spike_hours' => 72,
    'velocity_spike_multiplier' => 5,
    'velocity_spike_hours' => 2,
    'new_account_days' => 7,
    'new_account_high_value_minor' => 500_000_00,
    'same_ip_account_threshold' => 3,
    'same_ip_hours' => 168,
    'review_velocity_count' => 5,
    'review_velocity_hours' => 24,
    'reciprocal_review_hours' => 6,
    'cancellation_threshold' => 5,
    'cancellation_days' => 14,
    'payment_method_change_threshold' => 3,
    'payment_method_change_days' => 7,
    'refund_rate_threshold_percent' => 30,
    'refund_rate_days' => 30,
    'location_subnet_change_threshold' => 2,
    'location_subnet_hours' => 24,
    'device_inactivity_days' => 180,
    'auto_resolve_low_risk_days' => 14,

    // Escrow round-tripping (fund escrow -> mark complete -> release with no real work).
    'round_trip_window_days' => 60,
    'round_trip_min_releases' => 3,
    'round_trip_max_messages' => 4,
];
