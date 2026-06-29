<?php

namespace App\Enums;

enum QuestDisputeCategory: string
{
    case DeliveryQuality = 'delivery_quality';
    case ScopeCommunication = 'scope_communication';
    case ScopeRequirements = 'scope_requirements';
    case ConductProfessionalism = 'conduct_professionalism';
    case ClientBehavior = 'client_behavior';
    case PaymentContract = 'payment_contract';
    case PaymentTerms = 'payment_terms';
    case AssessmentFeedback = 'assessment_feedback';
    case Legacy = 'legacy';

    public function label(): string
    {
        return match ($this) {
            self::DeliveryQuality => __('Delivery & quality'),
            self::ScopeCommunication => __('Scope & communication'),
            self::ScopeRequirements => __('Scope & requirements'),
            self::ConductProfessionalism => __('Conduct & professionalism'),
            self::ClientBehavior => __('Client behavior'),
            self::PaymentContract => __('Payment & contract'),
            self::PaymentTerms => __('Payment & terms'),
            self::AssessmentFeedback => __('Assessment & feedback'),
            self::Legacy => __('Other'),
        };
    }
}
