<?php

namespace App\Services\Admin\UserActivityPatrol;

use App\Enums\UserActivityPatrolActionType;
use App\Enums\UserActivityPatrolStatus;
use App\Models\AdminUserSanction;
use App\Models\PaymentEscrow;
use App\Models\StaffWatchlistItem;
use App\Models\User;
use App\Models\UserActivityPatrolAction;
use App\Models\UserActivityPatrolFlag;
use App\Models\UserActivityPatrolNote;
use App\Services\Admin\PaymentsEscrowAdminService;
use App\Services\AdminActivityLogger;
use App\Services\Operations\StaffUserManagementService;
use App\Services\Payments\EscrowPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserActivityPatrolActionService
{
    public function __construct(
        private readonly StaffUserManagementService $staffUsers,
        private readonly AdminActivityLogger $logger,
        private readonly UserActivityPatrolBroadcastService $broadcast,
        private readonly UserAccountMergeService $merges,
        private readonly PaymentsEscrowAdminService $escrowAdmin,
        private readonly EscrowPaymentService $escrowPayments,
    ) {}

    public function assign(UserActivityPatrolFlag $flag, User $actor): UserActivityPatrolFlag
    {
        $flag->forceFill([
            'assigned_to_id' => $actor->id,
            'status' => UserActivityPatrolStatus::UnderReview->value,
        ])->save();

        $this->logAction($flag->user_id, $flag->id, $actor, UserActivityPatrolActionType::Assign, 'Case assigned', [
            'assigned_to' => $actor->id,
        ]);
        $this->broadcast->dispatch('assigned', $flag->user_id, $flag->id);

        return $flag->fresh(['assignedTo', 'user']);
    }

    public function release(UserActivityPatrolFlag $flag, User $actor): UserActivityPatrolFlag
    {
        if ($flag->assigned_to_id !== $actor->id && $actor->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages([
                'action' => __('Only the assignee or Super Admin can release this case.'),
            ]);
        }

        $flag->forceFill([
            'assigned_to_id' => null,
            'status' => UserActivityPatrolStatus::Open->value,
        ])->save();

        $this->logAction($flag->user_id, $flag->id, $actor, UserActivityPatrolActionType::Release, 'Case released back to queue');
        $this->broadcast->dispatch('released', $flag->user_id, $flag->id);

        return $flag->fresh(['assignedTo', 'user']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function warn(User $user, User $actor, array $data, Request $request, bool $isSuperAdmin): void
    {
        $this->staffUsers->issueWarning($user, $actor, [
            'reason_code' => $data['warning_type'] ?? 'policy_violation',
            'notes' => $data['message'] ?? '',
        ], $request);

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Warn, $data['message'] ?? '', $data);
        $this->touchFlag($data['flag_id'] ?? null, UserActivityPatrolStatus::UnderReview);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function watchlist(User $user, User $actor, array $data, bool $isSuperAdmin): void
    {
        $days = match ($data['duration'] ?? '30d') {
            '14d' => 14,
            '90d' => 90,
            'indefinite' => 3650,
            default => 30,
        };

        StaffWatchlistItem::query()->create([
            'staff_user_id' => $actor->id,
            'visibility' => $isSuperAdmin ? 'all_staff' : 'private',
            'watchable_type' => User::class,
            'watchable_id' => $user->id,
            'label' => $user->username ?? $user->name,
            'reason' => $data['reason'] ?? 'user_activity_patrol',
            'review_by_date' => now()->addDays($days),
            'severity' => $data['severity'] ?? 'medium',
            'notes' => $data['notes'] ?? null,
        ]);

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Watchlist, $data['reason'] ?? 'Added to watchlist', $data);
        $this->touchFlag($data['flag_id'] ?? null, UserActivityPatrolStatus::Watchlisted);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function investigate(User $user, User $actor, array $data): void
    {
        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Investigate, $data['notes'] ?? '', [
            'title' => $data['title'] ?? "Investigation — {$user->username}",
            'severity' => $data['severity'] ?? 'medium',
            'assign_to' => $data['assign_to'] ?? $actor->id,
        ]);
        $this->touchFlag($data['flag_id'] ?? null, UserActivityPatrolStatus::UnderReview);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function message(User $user, User $actor, array $data, Request $request): void
    {
        $this->staffUsers->message($user, $actor, [
            'subject' => $data['subject'] ?? 'Account Review',
            'body' => $data['message'] ?? '',
        ], $request);

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Message, $data['message'] ?? '', $data);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * Temporary suspension — not permanent. Use terminate() for permanent removal.
     *
     * @param  array<string, mixed>  $data
     */
    public function suspend(User $user, User $actor, array $data, Request $request): void
    {
        $this->assertSuperAdmin($actor);

        DB::transaction(function () use ($user, $actor, $data): void {
            $duration = $data['duration'] ?? '7d';
            $endsAt = $duration === '30d' ? now()->addDays(30) : now()->addDays(7);

            AdminUserSanction::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $actor->id,
                'type' => 'suspension',
                'reason_code' => $data['reason'] ?? 'policy_violation',
                'notes' => $data['notes'] ?? '',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            $user->forceFill(['suspended_at' => now()])->save();
        });

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Suspend, $data['notes'] ?? 'Account suspended', $data);
        $this->logger->log($actor, 'user_activity_patrol.suspend', User::class, $user->id, $data, $request);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * Permanent account termination — distinct from temporary suspend.
     *
     * @param  array<string, mixed>  $data
     */
    public function terminate(User $user, User $actor, array $data, Request $request): void
    {
        $this->assertSuperAdmin($actor);

        DB::transaction(function () use ($user, $actor, $data): void {
            AdminUserSanction::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $actor->id,
                'type' => 'ban',
                'reason_code' => $data['reason'] ?? 'fraud_detected',
                'notes' => $data['notes'] ?? 'Account terminated by Super Admin',
                'starts_at' => now(),
                'ends_at' => null,
            ]);

            $user->forceFill([
                'banned_at' => now(),
                'ban_reason' => $data['notes'] ?? 'Account terminated',
                'deactivated_at' => now(),
                'suspended_at' => now(),
            ])->save();
        });

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Terminate, $data['notes'] ?? 'Account terminated', $data);
        $this->logger->log($actor, 'user_activity_patrol.terminate', User::class, $user->id, $data, $request);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function imposeSanction(User $user, User $actor, array $data, Request $request): void
    {
        $this->assertSuperAdmin($actor);

        $type = $data['sanction_type'] ?? 'restriction';
        $endsAt = match ($data['duration'] ?? '30d') {
            '7d' => now()->addDays(7),
            '90d' => now()->addDays(90),
            'indefinite' => null,
            default => now()->addDays(30),
        };

        DB::transaction(function () use ($user, $actor, $data, $type, $endsAt): void {
            AdminUserSanction::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $actor->id,
                'type' => $type,
                'reason_code' => $data['reason'] ?? 'policy_violation',
                'notes' => $data['notes'] ?? '',
                'starts_at' => now(),
                'ends_at' => $endsAt,
            ]);

            if ($type === 'restriction') {
                $user->forceFill(['under_review_at' => now()])->save();
            } elseif ($type === 'ban') {
                $user->forceFill([
                    'banned_at' => now(),
                    'ban_reason' => $data['notes'] ?? 'Sanction imposed',
                    'deactivated_at' => now(),
                ])->save();
            } elseif ($type === 'suspension') {
                $user->forceFill(['suspended_at' => now()])->save();
            }
        });

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::ImposeSanction, $data['notes'] ?? '', $data);
        $this->logger->log($actor, 'user_activity_patrol.sanction', User::class, $user->id, $data, $request);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reverseTransaction(User $user, User $actor, array $data, Request $request): void
    {
        $this->assertSuperAdmin($actor);

        $escrow = PaymentEscrow::query()
            ->whereKey($data['escrow_id'] ?? 0)
            ->where(function ($q) use ($user): void {
                $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id);
            })
            ->firstOrFail();

        $reason = $data['notes'] ?? ($data['reason'] ?? 'Patrol reversal');
        $reverseType = $data['reverse_type'] ?? 'full';

        if ($reverseType === 'partial' && ! empty($data['partial_amount_minor'])) {
            $quest = $escrow->quest;
            if ($quest === null) {
                throw ValidationException::withMessages(['escrow_id' => __('Quest missing for escrow.')]);
            }
            $this->escrowPayments->refundEscrow($quest, $actor, $reason, (int) $data['partial_amount_minor']);
        } else {
            $this->escrowAdmin->forceRefund($escrow, $actor, $reason);
        }

        if (! empty($data['suspend_account'])) {
            $this->suspend($user, $actor, ['reason' => 'fraud_detected', 'duration' => '7d', 'notes' => 'Auto-suspended after reversal'], $request);
        }

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::ReverseTransaction, $reason, [
            'escrow_id' => $escrow->id,
            'reverse_type' => $reverseType,
        ]);
        $this->broadcast->dispatch('action', $user->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mergeAccounts(User $primary, User $actor, array $data): void
    {
        $this->assertSuperAdmin($actor);

        $secondary = User::query()->findOrFail($data['secondary_user_id'] ?? 0);
        $merge = $this->merges->merge($primary, $secondary, $actor, $data);

        $this->logAction($primary->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::MergeAccounts, $data['notes'] ?? 'Accounts merged', [
            'secondary_user_id' => $secondary->id,
            'merge_id' => $merge->id,
        ]);
        $this->broadcast->dispatch('merged', $primary->id, $data['flag_id'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addNote(User $user, User $actor, array $data): UserActivityPatrolNote
    {
        $note = UserActivityPatrolNote::query()->create([
            'user_id' => $user->id,
            'flag_id' => $data['flag_id'] ?? null,
            'author_id' => $actor->id,
            'body' => $data['body'],
        ]);

        $this->logAction($user->id, $data['flag_id'] ?? null, $actor, UserActivityPatrolActionType::Note, $data['body']);
        $this->broadcast->dispatch('note', $user->id, $data['flag_id'] ?? null);

        return $note;
    }

    public function resolve(UserActivityPatrolFlag $flag, User $actor, string $notes = ''): void
    {
        $flag->forceFill([
            'status' => UserActivityPatrolStatus::Resolved->value,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
        ])->save();

        $this->logAction($flag->user_id, $flag->id, $actor, UserActivityPatrolActionType::Resolve, $notes);
        $this->broadcast->dispatch('resolved', $flag->user_id, $flag->id);
    }

    public function dismiss(UserActivityPatrolFlag $flag, User $actor, string $reason): void
    {
        $flag->forceFill([
            'status' => UserActivityPatrolStatus::Dismissed->value,
            'resolved_at' => now(),
            'resolved_by_id' => $actor->id,
        ])->save();

        $this->logAction($flag->user_id, $flag->id, $actor, UserActivityPatrolActionType::Dismiss, $reason);
        $this->broadcast->dispatch('dismissed', $flag->user_id, $flag->id);
    }

    private function assertSuperAdmin(User $actor): void
    {
        if ($actor->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages([
                'action' => __('Only Super Admin can perform this action.'),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function logAction(
        int $userId,
        ?int $flagId,
        User $actor,
        UserActivityPatrolActionType $type,
        ?string $notes = null,
        array $meta = [],
    ): void {
        UserActivityPatrolAction::query()->create([
            'user_id' => $userId,
            'flag_id' => $flagId,
            'action_type' => $type->value,
            'actor_id' => $actor->id,
            'reason_notes' => $notes,
            'meta' => $meta ?: null,
            'occurred_at' => now(),
        ]);
    }

    private function touchFlag(?int $flagId, UserActivityPatrolStatus $status): void
    {
        if (! $flagId) {
            return;
        }

        UserActivityPatrolFlag::query()->whereKey($flagId)->update(['status' => $status->value]);
    }
}
