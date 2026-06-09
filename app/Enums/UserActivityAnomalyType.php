<?php

namespace App\Enums;

enum UserActivityAnomalyType: string
{
    case DisputeSpike = 'dispute_spike';
    case OffPlatformPayment = 'off_platform_payment';
    case VelocitySpike = 'velocity_spike';
    case VerificationFail = 'verification_fail';
    case Chargeback = 'chargeback';
    case AccountInconsistency = 'account_inconsistency';
    case LocationAnomaly = 'location_anomaly';
    case DeviceAnomaly = 'device_anomaly';
    case PaymentIssue = 'payment_issue';
    case ReviewManipulation = 'review_manipulation';
    case ConversationFlag = 'conversation_flag';
    case NewAccountHighValue = 'new_account_high_value';
    case WinRateAnomaly = 'win_rate_anomaly';
    case CancellationPattern = 'cancellation_pattern';
    case SharedIdentity = 'shared_identity';
    case SharedIpAccounts = 'shared_ip_accounts';
    case PremiumAnomaly = 'premium_anomaly';
    case RefundRateHigh = 'refund_rate_high';
    case TrustScoreDrop = 'trust_score_drop';

    public function label(): string
    {
        return match ($this) {
            self::DisputeSpike => 'Dispute Spike',
            self::OffPlatformPayment => 'Off-Platform Payment',
            self::VelocitySpike => 'Velocity Spike',
            self::VerificationFail => 'Verification Fail',
            self::Chargeback => 'Chargeback',
            self::AccountInconsistency => 'Account Inconsistency',
            self::LocationAnomaly => 'Location Anomaly',
            self::DeviceAnomaly => 'Device Anomaly',
            self::PaymentIssue => 'Payment Issue',
            self::ReviewManipulation => 'Review Manipulation',
            self::ConversationFlag => 'Conversation Flag',
            self::NewAccountHighValue => 'New Account High Value',
            self::WinRateAnomaly => 'Win Rate Anomaly',
            self::CancellationPattern => 'Cancellation Pattern',
            self::SharedIdentity => 'Shared Identity',
            self::SharedIpAccounts => 'Shared IP Accounts',
            self::PremiumAnomaly => 'Premium Anomaly',
            self::RefundRateHigh => 'Refund Rate High',
            self::TrustScoreDrop => 'Trust Score Drop',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::DisputeSpike, self::Chargeback, self::RefundRateHigh, self::PaymentIssue => 'financial',
            self::VelocitySpike, self::NewAccountHighValue, self::WinRateAnomaly, self::CancellationPattern, self::PremiumAnomaly, self::TrustScoreDrop => 'behavioral',
            self::OffPlatformPayment, self::ConversationFlag => 'communication',
            self::VerificationFail, self::LocationAnomaly, self::DeviceAnomaly, self::SharedIdentity, self::SharedIpAccounts => 'verification',
            self::ReviewManipulation => 'review',
            self::AccountInconsistency => 'account',
        };
    }
}
