<?php

return [
    'cache' => [
        'metrics_ttl_seconds' => (int) env('FINANCIAL_HEALTH_METRICS_TTL', 300),
        'charts_ttl_seconds' => (int) env('FINANCIAL_HEALTH_CHARTS_TTL', 3600),
    ],

    'alerts' => [
        'escrow_variance_critical_minor' => 500_000,
        'escrow_variance_warning_minor' => 100_000,
        'overdue_release_hours' => 48,
        'vat_remittance_critical_days' => 3,
        'vat_remittance_warning_days' => 7,
        'payment_due_soon_hours' => 24,
    ],

    'processor_fee_percent' => (float) env('FINANCIAL_HEALTH_PROCESSOR_FEE_PERCENT', 1.5),

    'monthly_revenue_budget_minor' => (int) env('FINANCIAL_HEALTH_MONTHLY_BUDGET_MINOR', 5_000_000_00),

    'vat_percent' => (float) env('FINANCIAL_HEALTH_VAT_PERCENT', 7.5),

    'period_presets' => [
        'today' => 'Today',
        'week' => 'This week',
        'month' => 'This month',
        'custom' => 'Custom range',
    ],

    'chart_grain_presets' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
    ],

    'payment_horizon_presets' => [
        'today' => 'Today',
        'next_7_days' => 'Next 7 days',
        'month' => 'This month',
    ],
];
