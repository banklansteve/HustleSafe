<?php

use App\Models\Quest;
use App\Models\QuestConversationThread;
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

Broadcast::channel('admin.live-activity', function ($user) {
    return $user !== null && $user->role?->slug === 'super_admin';
});
