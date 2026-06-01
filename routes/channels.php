<?php

use App\Models\ProposalClarificationThread;
use App\Models\Quest;
use App\Models\QuestConversationThread;
use App\Models\StaffTeamChatRoom;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('quests.{questId}.client', function ($user, string $questId) {
    if ($user === null) {
        return false;
    }

    $quest = Quest::query()->find($questId);

    return $quest !== null && (int) $user->id === (int) $quest->client_id;
});

Broadcast::channel('quest-threads.{threadId}', function ($user, string $threadId) {
    if ($user === null) {
        return false;
    }

    $thread = QuestConversationThread::query()->find($threadId);
    if ($thread === null) {
        return false;
    }

    return (int) $user->id === (int) $thread->client_id
        || (int) $user->id === (int) $thread->freelancer_id;
});

Broadcast::channel('proposal-clarifications.{threadId}', function ($user, string $threadId) {
    if ($user === null) {
        return false;
    }

    $thread = ProposalClarificationThread::query()->find($threadId);
    if ($thread === null) {
        return false;
    }

    return (int) $user->id === (int) $thread->client_id
        || (int) $user->id === (int) $thread->freelancer_id;
});

Broadcast::channel('admin.live-activity', function ($user) {
    return $user !== null && $user->role?->slug === 'super_admin';
});

Broadcast::channel('admin-dm.{conversationId}', function ($user, string $conversationId) {
    if ($user === null) {
        return false;
    }

    $user->loadMissing('role');

    if (! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
        return false;
    }

    return \App\Models\AdminDirectConversation::query()
        ->whereKey((int) $conversationId)
        ->where(function ($q) use ($user): void {
            $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id);
        })
        ->exists();
});

Broadcast::channel('customer-support.staff', function ($user) {
    if ($user === null) {
        return false;
    }

    $user->loadMissing('role');

    return in_array($user->role?->slug, ['admin', 'super_admin'], true);
});

Broadcast::channel('verification.staff', function ($user) {
    if ($user === null) {
        return false;
    }

    $user->loadMissing('role');

    return in_array($user->role?->slug, ['admin', 'super_admin'], true);
});

Broadcast::channel('customer-support.{ticketId}', function ($user, string $ticketId) {
    if ($user === null) {
        return false;
    }

    $ticket = \App\Models\SupportTicket::query()
        ->where('uuid', $ticketId)
        ->orWhere('id', $ticketId)
        ->first();
    if ($ticket === null) {
        return false;
    }

    if ((int) $ticket->user_id === (int) $user->id) {
        return true;
    }

    return app(\App\Services\Support\CustomerSupportService::class)->canAccessTicket($ticket, $user);
});

Broadcast::channel('staff-team.{roomId}', function ($user, string $roomId) {
    if ($user === null) {
        return false;
    }

    $user->loadMissing('role');

    if (! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
        return false;
    }

    return StaffTeamChatRoom::query()->whereKey((int) $roomId)->exists();
});
