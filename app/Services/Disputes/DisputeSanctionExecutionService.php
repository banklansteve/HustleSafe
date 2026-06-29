<?php

namespace App\Services\Disputes;

use App\Models\AdminUserSanction;
use App\Models\DisputeEvent;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DisputeSanctionExecutionService
{
    public function execute(QuestDispute $dispute, User $actor, array $sanctions): void
    {
        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $client = $dispute->quest?->client;
        $freelancer = $dispute->quest?->freelancer;
        $notes = __('Sanction from dispute :ref', ['ref' => $dispute->displayReference()]);

        DB::transaction(function () use ($sanctions, $actor, $client, $freelancer, $notes, $dispute): void {
            if (! empty($sanctions['warn_client']) && $client) {
                $this->warning($client, $actor, $notes, $dispute);
            }
            if (! empty($sanctions['warn_freelancer']) && $freelancer) {
                $this->warning($freelancer, $actor, $notes, $dispute);
            }

            $targetId = (int) ($sanctions['target_user_id'] ?? $sanctions['suspend_user_id'] ?? 0);
            $target = $this->resolveTarget($dispute, $targetId);
            $sanctionType = (string) ($sanctions['type'] ?? '');

            if ($target && in_array($sanctionType, ['suspend_7', 'suspend_30', 'suspension'], true)) {
                $days = $sanctionType === 'suspend_30' ? 30 : 7;
                $this->suspend($target, $actor, $days, $notes, $dispute);
            } elseif ($target && $sanctionType === 'permanent_ban') {
                $this->ban($target, $actor, $notes, $dispute);
            } elseif ($target && $sanctionType === 'tier_demotion') {
                $this->demoteTier($target, $actor, $dispute);
            } elseif ($target && $sanctionType === 'category_ban') {
                $this->categoryBan($target, $actor, $sanctions['category_id'] ?? null, $notes, $dispute);
            }
        });
    }

    private function resolveTarget(QuestDispute $dispute, int $userId): ?User
    {
        if ($userId <= 0) {
            return null;
        }

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        if ((int) $dispute->quest?->client_id === $userId) {
            return $dispute->quest->client;
        }
        if ((int) $dispute->quest?->freelancer_id === $userId) {
            return $dispute->quest->freelancer;
        }

        return User::query()->find($userId);
    }

    private function warning(User $user, User $actor, string $notes, QuestDispute $dispute): void
    {
        AdminUserSanction::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $actor->id,
            'type' => 'warning',
            'reason_code' => 'dispute_resolution',
            'notes' => $notes,
            'starts_at' => now(),
        ]);

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'sanction.warning_issued',
            'properties' => ['user_id' => $user->id],
            'created_at' => now(),
        ]);
    }

    private function suspend(User $user, User $actor, int $days, string $notes, QuestDispute $dispute): void
    {
        $endsAt = now()->addDays($days);
        AdminUserSanction::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $actor->id,
            'type' => 'suspension',
            'reason_code' => 'dispute_resolution',
            'notes' => $notes,
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ]);
        $user->forceFill(['suspended_at' => now()])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'sanction.suspension_issued',
            'properties' => ['user_id' => $user->id, 'days' => $days, 'ends_at' => $endsAt->toIso8601String()],
            'created_at' => now(),
        ]);
    }

    private function ban(User $user, User $actor, string $notes, QuestDispute $dispute): void
    {
        AdminUserSanction::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $actor->id,
            'type' => 'ban',
            'reason_code' => 'dispute_resolution',
            'notes' => $notes,
            'starts_at' => now(),
        ]);
        $user->forceFill(['banned_at' => now(), 'ban_reason' => $notes])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'sanction.ban_issued',
            'properties' => ['user_id' => $user->id],
            'created_at' => now(),
        ]);
    }

    private function demoteTier(User $user, User $actor, QuestDispute $dispute): void
    {
        $tiers = ['elite', 'verified', 'basic', 'unverified'];
        $current = strtolower((string) ($user->verification_tier ?? 'unverified'));
        $index = array_search($current, $tiers, true);
        $next = $index !== false && $index < count($tiers) - 1 ? $tiers[$index + 1] : 'unverified';
        $user->forceFill(['verification_tier' => $next])->save();

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'sanction.tier_demoted',
            'properties' => ['user_id' => $user->id, 'from' => $current, 'to' => $next],
            'created_at' => now(),
        ]);
    }

    private function categoryBan(User $user, User $actor, mixed $categoryId, string $notes, QuestDispute $dispute): void
    {
        $state = $user->account_restrictions ?? [];
        if (! is_array($state)) {
            $state = [];
        }
        $banned = array_values(array_unique(array_filter([...(array) ($state['banned_category_ids'] ?? []), (int) $categoryId])));
        $state['banned_category_ids'] = $banned;
        if (Schema::hasColumn('users', 'account_restrictions')) {
            $user->forceFill(['account_restrictions' => $state])->save();
        }

        AdminUserSanction::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $actor->id,
            'type' => 'category_ban',
            'reason_code' => 'dispute_resolution',
            'notes' => $notes,
            'starts_at' => now(),
        ]);

        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => 'sanction.category_ban',
            'properties' => ['user_id' => $user->id, 'category_id' => $categoryId],
            'created_at' => now(),
        ]);
    }
}
