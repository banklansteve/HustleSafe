<?php

namespace App\Services\Disputes;

class DisputeResolutionOutcomeLabelService
{
    public function label(?string $outcome): ?string
    {
        if ($outcome === null || $outcome === '') {
            return null;
        }

        return match ($outcome) {
            'settlement_accepted' => __('Resolved by agreed payment split'),
            'mutual_resolve' => __('Resolved by mutual agreement'),
            'auto_timed_split' => __('Auto-resolved after timer expired'),
            'super_admin_decision' => __('Resolved by Super Admin decision'),
            'refund_cancel' => __('Refund and job cancelled'),
            default => str_replace('_', ' ', ucfirst($outcome)),
        };
    }
}
