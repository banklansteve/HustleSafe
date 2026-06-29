<?php

namespace App\Enums;

enum DisputeNegotiationOfferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Countered = 'countered';
    case Rejected = 'rejected';
    case Expired = 'expired';
    case Superseded = 'superseded';
}
