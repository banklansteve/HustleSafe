<?php

namespace App\Enums;

enum UserActivityPatrolActionType: string
{
    case Warn = 'warn';
    case Watchlist = 'watchlist';
    case Investigate = 'investigate';
    case Message = 'message';
    case Assign = 'assign';
    case Release = 'release';
    case Suspend = 'suspend';
    case Terminate = 'terminate';
    case ReverseTransaction = 'reverse_transaction';
    case MergeAccounts = 'merge_accounts';
    case ImposeSanction = 'impose_sanction';
    case Dismiss = 'dismiss';
    case Resolve = 'resolve';
    case Note = 'note';
    case StatusChange = 'status_change';
}
