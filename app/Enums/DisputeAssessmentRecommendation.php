<?php

namespace App\Enums;

enum DisputeAssessmentRecommendation: string
{
    case AwardClientFull = 'award_client_full';
    case AwardFreelancerFull = 'award_freelancer_full';
    case PartialAward = 'partial_award';
    case ForceRevision = 'force_revision';
    case MediationNeeded = 'mediation_needed';
    case ExtendDeadline = 'extend_deadline';
    case RefundCancel = 'refund_cancel';

    public function label(): string
    {
        return match ($this) {
            self::AwardClientFull => __('Award to client (full refund)'),
            self::AwardFreelancerFull => __('Award to freelancer (full payment)'),
            self::PartialAward => __('Split the payment'),
            self::ForceRevision => __('Give another chance to fix the work'),
            self::MediationNeeded => __('Talk with a mediator'),
            self::ExtendDeadline => __('More time to finish the work'),
            self::RefundCancel => __('Refund client and cancel the job'),
        };
    }

    public function toResolutionOption(): DisputeResolutionOption
    {
        return match ($this) {
            self::AwardClientFull => DisputeResolutionOption::AwardClientFull,
            self::AwardFreelancerFull => DisputeResolutionOption::AwardFreelancerFull,
            self::PartialAward => DisputeResolutionOption::SplitFund,
            self::ForceRevision => DisputeResolutionOption::ForceRevision,
            self::MediationNeeded => DisputeResolutionOption::Mediation,
            self::ExtendDeadline => DisputeResolutionOption::ExtendDeadline,
            self::RefundCancel => DisputeResolutionOption::RefundCancel,
        };
    }
}
