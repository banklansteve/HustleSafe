<?php

namespace App\Enums;

enum DisputeMessageKind: string
{
    case Narrative = 'narrative';
    case Evidence = 'evidence';
    case StructuredResponse = 'structured_response';
    case SettlementNote = 'settlement_note';
    case System = 'system';
}
