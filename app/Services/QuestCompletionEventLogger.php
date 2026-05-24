<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\QuestCompletionEvent;
use App\Models\User;
use Illuminate\Http\Request;

class QuestCompletionEventLogger
{
    public function record(
        Quest $quest,
        string $eventType,
        ?User $actor = null,
        ?Request $request = null,
        array $meta = [],
    ): QuestCompletionEvent {
        $req = $request ?? request();

        $event = QuestCompletionEvent::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $quest->accepted_quest_offer_id,
            'actor_user_id' => $actor?->id,
            'event_type' => $eventType,
            'ip_address' => $req?->ip(),
            'user_agent' => $req ? substr((string) $req->userAgent(), 0, 512) : null,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);

        if ($actor !== null) {
            app(AdminActivityLogger::class)->log(
                $actor,
                'quest.completion.'.$eventType,
                Quest::class,
                $quest->id,
                array_merge($meta, ['quest_title' => $quest->title, 'event_id' => $event->id]),
                $request,
            );
        } elseif (in_array($eventType, ['auto_completed', 'auto_funds_released', 'escrow_funded'], true)) {
            \App\Models\AdminActivityLog::query()->create([
                'actor_user_id' => null,
                'action' => 'quest.completion.'.$eventType,
                'subject_type' => Quest::class,
                'subject_id' => $quest->id,
                'properties' => array_merge($meta, [
                    'quest_title' => $quest->title,
                    'event_id' => $event->id,
                    'actor_label' => 'system',
                ]),
                'ip_address' => null,
                'user_agent' => 'system',
            ]);
        }

        return $event;
    }
}
