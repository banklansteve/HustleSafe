<?php

namespace App\Enums;

enum DisputeNegotiationPhase: string
{
    case PeerNegotiation = 'peer_negotiation';
    case AwaitingMutualApproval = 'awaiting_mutual_approval';
    case EscalatingToMediation = 'escalating_to_mediation';
    case Mediation = 'mediation';
    case AwaitingEnforcement = 'awaiting_enforcement';
    case AppealOpen = 'appeal_open';
    case AppealUnderReview = 'appeal_under_review';
    case Final = 'final';

    public function label(): string
    {
        return match ($this) {
            self::PeerNegotiation => __('Peer negotiation'),
            self::AwaitingMutualApproval => __('Awaiting admin approval'),
            self::EscalatingToMediation => __('Escalating to mediation'),
            self::Mediation => __('Mediation'),
            self::AwaitingEnforcement => __('Decision pending acceptance'),
            self::AppealOpen => __('Appeal window open'),
            self::AppealUnderReview => __('Appeal under review'),
            self::Final => __('Final'),
        };
    }

    public function partyHeadline(): string
    {
        return match ($this) {
            self::PeerNegotiation => __('Negotiate with the other party'),
            self::AwaitingMutualApproval => __('You agreed — waiting for Customer Support approval'),
            self::EscalatingToMediation => __('Negotiation ended — mediator reviewing'),
            self::Mediation => __('Staff mediator is reviewing your case'),
            self::AwaitingEnforcement => __('A decision was issued — you may accept or reject within the window'),
            self::AppealOpen => __('You may appeal this decision once'),
            self::AppealUnderReview => __('Appeal under review'),
            self::Final => __('Dispute finalized'),
        };
    }
}
