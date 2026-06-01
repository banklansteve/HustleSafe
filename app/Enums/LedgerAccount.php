<?php

namespace App\Enums;

enum LedgerAccount: string
{
    case ClientEscrowLiability = 'client_escrow_liability';
    case FreelancerPayable = 'freelancer_payable';
    case PlatformFeeRevenue = 'platform_fee_revenue';
    case VatPayable = 'vat_payable';
    case PaymentGatewaySuspense = 'payment_gateway_suspense';
    case RefundPayable = 'refund_payable';
    case WithdrawalClearing = 'withdrawal_clearing';
    case PromotionalSpend = 'promotional_spend';
    case PromotionalSpendClearing = 'promotional_spend_clearing';

    public function label(): string
    {
        return match ($this) {
            self::ClientEscrowLiability => 'Client Escrow Liability',
            self::FreelancerPayable => 'Freelancer Payable',
            self::PlatformFeeRevenue => 'Platform Fee Revenue',
            self::VatPayable => 'VAT Payable',
            self::PaymentGatewaySuspense => 'Payment Gateway Suspense',
            self::RefundPayable => 'Refund Payable',
            self::WithdrawalClearing => 'Withdrawal Clearing',
            self::PromotionalSpend => 'Promotional Spend',
            self::PromotionalSpendClearing => 'Promotional Spend Clearing',
        };
    }

    /**
     * @return list<self>
     */
    public static function normalBalanceSide(): array
    {
        return [
            self::ClientEscrowLiability,
            self::FreelancerPayable,
            self::VatPayable,
            self::PaymentGatewaySuspense,
            self::RefundPayable,
            self::WithdrawalClearing,
            self::PromotionalSpendClearing,
        ];
    }
}
