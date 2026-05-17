<?php

namespace App\Enums;

enum QuestDisputeStatus: string
{
    case Open = 'open';
    case SelfResolving = 'self_resolving';
    case Escalated = 'escalated';
    case AwaitingRuling = 'awaiting_ruling';
    case Resolved = 'resolved';
    case ClosedWithdrawn = 'closed_withdrawn';
}
