<?php

namespace App\Enums;

enum DisputeSettlementOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Superseded = 'superseded';
}
