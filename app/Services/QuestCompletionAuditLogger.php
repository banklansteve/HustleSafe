<?php

namespace App\Services;

use App\Models\Quest;
use App\Models\User;
use Illuminate\Http\Request;

class QuestCompletionAuditLogger
{
    public function logClientMarkedComplete(Quest $quest, User $client, Request $request): void
    {
        $this->write($client, 'quest.completion.client_marked', $quest, [
            'closure_type' => 'client_marked_complete',
            'escrow_status' => $quest->escrow_status,
            'escrow_funded_at' => $quest->escrow_funded_at?->toIso8601String(),
            'cooldown_hours' => \App\Support\EscrowReleaseCooldown::cooldownHours(),
        ], $request);
    }

    public function logAutoCompleted(Quest $quest): void
    {
        $this->write(null, 'quest.completion.auto_completed', $quest, [
            'closure_type' => 'auto_completed_silent_72h',
            'auto_completed_at' => $quest->auto_completed_at?->toIso8601String(),
        ]);
    }

    public function logAdminEarlyRelease(Quest $quest, User $admin, string $reason, Request $request): void
    {
        $this->write($admin, 'quest.escrow.released_admin_override', $quest, [
            'reason' => $reason,
            'cooldown_hours' => \App\Support\EscrowReleaseCooldown::cooldownHours(),
            'escrow_funded_at' => $quest->escrow_funded_at?->toIso8601String(),
            'bypassed_cooldown' => true,
        ], $request);
    }

    protected function write(?User $actor, string $action, Quest $quest, array $properties, ?Request $request = null): void
    {
        if ($actor === null) {
            \App\Models\AdminActivityLog::query()->create([
                'actor_user_id' => null,
                'action' => $action,
                'subject_type' => Quest::class,
                'subject_id' => $quest->id,
                'properties' => array_merge($properties, [
                    'quest_title' => $quest->title,
                    'client_id' => $quest->client_id,
                    'freelancer_id' => $quest->freelancer_id,
                    'actor_label' => 'system',
                ]),
                'ip_address' => null,
                'user_agent' => 'quests:process-lifecycle',
            ]);

            return;
        }

        app(AdminActivityLogger::class)->log(
            $actor,
            $action,
            Quest::class,
            $quest->id,
            array_merge($properties, ['quest_title' => $quest->title]),
            $request,
        );
    }
}
