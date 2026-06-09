<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Models\UserAccountMerge;
use App\Models\UserActivityPatrolFlag;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserAccountMergeService
{
    public function merge(User $primary, User $secondary, User $actor, array $data): UserAccountMerge
    {
        $this->guardMergeable($primary, $secondary);

        return DB::transaction(function () use ($primary, $secondary, $actor, $data): UserAccountMerge {
            Quest::query()
                ->where('client_id', $secondary->id)
                ->update(['client_id' => $primary->id]);

            Quest::query()
                ->where('freelancer_id', $secondary->id)
                ->update(['freelancer_id' => $primary->id]);

            QuestOffer::query()
                ->where('freelancer_id', $secondary->id)
                ->update(['freelancer_id' => $primary->id]);

            UserActivityPatrolFlag::query()
                ->where('user_id', $secondary->id)
                ->whereIn('status', ['open', 'under_review', 'watchlisted'])
                ->update(['user_id' => $primary->id]);

            $merge = UserAccountMerge::query()->create([
                'primary_user_id' => $primary->id,
                'secondary_user_id' => $secondary->id,
                'actor_id' => $actor->id,
                'reason_code' => $data['reason'] ?? 'fraud_ring',
                'reason_notes' => $data['notes'] ?? null,
                'meta' => [
                    'secondary_username' => $secondary->username,
                    'secondary_email' => $secondary->email,
                ],
                'merged_at' => now(),
            ]);

            $secondary->forceFill([
                'deactivated_at' => now(),
                'banned_at' => now(),
                'ban_reason' => 'Merged into account #'.$primary->id.' ('.($data['notes'] ?? 'fraud investigation').')',
                'suspended_at' => now(),
            ])->save();

            return $merge;
        });
    }

    private function guardMergeable(User $primary, User $secondary): void
    {
        if ($primary->id === $secondary->id) {
            throw ValidationException::withMessages(['secondary_user_id' => __('Cannot merge an account with itself.')]);
        }

        foreach ([$primary, $secondary] as $user) {
            if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
                throw ValidationException::withMessages(['user' => __('Admin accounts cannot be merged.')]);
            }
        }

        if (UserAccountMerge::query()->where('secondary_user_id', $secondary->id)->exists()) {
            throw ValidationException::withMessages(['secondary_user_id' => __('This account was already merged.')]);
        }
    }
}
