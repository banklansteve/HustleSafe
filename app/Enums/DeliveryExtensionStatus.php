<?php

namespace App\Enums;

enum DeliveryExtensionStatus: string
{
    case PendingClient = 'pending_client';
    case CounterProposed = 'counter_proposed';
    case Approved = 'approved';
    case Declined = 'declined';
    case AutoApproved = 'auto_approved';
    case CounterRejected = 'counter_rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingClient => __('Awaiting client response'),
            self::CounterProposed => __('Counter-proposal awaiting freelancer'),
            self::Approved => __('Approved'),
            self::Declined => __('Declined'),
            self::AutoApproved => __('Auto-approved'),
            self::CounterRejected => __('Counter-proposal rejected'),
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [
            self::Approved,
            self::Declined,
            self::AutoApproved,
            self::CounterRejected,
        ], true);
    }
}
