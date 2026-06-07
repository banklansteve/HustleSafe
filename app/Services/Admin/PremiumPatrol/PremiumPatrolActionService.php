<?php

namespace App\Services\Admin\PremiumPatrol;

use App\Enums\AdminQuestStatus;
use App\Enums\FreelancerSubscriptionStatus;
use App\Enums\FreelancerSubscriptionTier;
use App\Enums\PremiumPatrolActionType;
use App\Enums\PremiumPatrolFlagStatus;
use App\Enums\PremiumPatrolSubjectType;
use App\Enums\QuestBoostStatus;
use App\Enums\QuestBoostTier;
use App\Enums\QuestStatus;
use App\Enums\SubscriptionBillingCycle;
use App\Models\FreelancerSubscription;
use App\Models\FreelancerSubscriptionPayment;
use App\Models\PremiumPatrolAction;
use App\Models\PremiumPatrolFlag;
use App\Models\PremiumPatrolInvestigation;
use App\Models\PremiumPatrolWatchlist;
use App\Models\Quest;
use App\Models\QuestBoost;
use App\Models\User;
use App\Notifications\FreelancerProAdminGrantedNotification;
use App\Notifications\FreelancerProRefundedNotification;
use App\Notifications\FreelancerProSuspendedNotification;
use App\Notifications\QuestBoostAdminGrantedNotification;
use App\Notifications\QuestBoostDemotedNotification;
use App\Notifications\QuestBoostRefundedNotification;
use App\Notifications\QuestBoostVerificationRequestNotification;
use App\Notifications\QuestSuspendedByAdminNotification;
use App\Services\Admin\QuestBoostService;
use App\Services\Freelancer\FreelancerProSubscriptionService;
use App\Services\Payments\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PremiumPatrolActionService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly FreelancerProSubscriptionService $proService,
        private readonly QuestBoostService $boostService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function suspendPremium(User $user, User $admin, array $data): FreelancerSubscription
    {
        return DB::transaction(function () use ($user, $admin, $data): FreelancerSubscription {
            $subscription = $this->proService->subscriptionFor($user);
            if ($subscription->tier !== FreelancerSubscriptionTier::Pro->value) {
                throw ValidationException::withMessages(['user' => [__('User does not have active premium.')]]);
            }

            $refundMinor = $this->proratedRefundMinor($subscription);
            if ($refundMinor > 0) {
                $this->wallets->credit(
                    $user,
                    $refundMinor,
                    'premium_refund_prorated',
                    "premium-refund-prorated-{$subscription->id}-".now()->timestamp,
                    ['subscription_id' => $subscription->id],
                    description: __('Prorated premium refund after admin suspension.'),
                    admin: $admin,
                );
            }

            $fromStatus = $subscription->status;
            $subscription->forceFill([
                'status' => FreelancerSubscriptionStatus::AdminSuspended->value,
                'tier' => FreelancerSubscriptionTier::Free->value,
                'admin_suspended_at' => now(),
                'admin_suspended_by_id' => $admin->id,
                'admin_suspension_reason' => (string) ($data['reason_notes'] ?? $data['reason_code']),
                'renewal_date' => null,
                'auto_renew' => false,
            ])->save();

            $this->logAction(PremiumPatrolSubjectType::PremiumUser, $user->id, PremiumPatrolActionType::SuspendPremium, $admin, $data, [
                'refund_minor' => $refundMinor,
                'from_status' => $fromStatus,
            ]);

            $user->notify(new FreelancerProSuspendedNotification($subscription, (string) ($data['reason_notes'] ?? $data['reason_code'])));

            return $subscription->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function refundPremium(User $user, User $admin, array $data): FreelancerSubscription
    {
        return DB::transaction(function () use ($user, $admin, $data): FreelancerSubscription {
            $subscription = $this->proService->subscriptionFor($user);
            $payment = FreelancerSubscriptionPayment::query()
                ->where('user_id', $user->id)
                ->where('status', 'paid')
                ->orderByDesc('paid_at')
                ->first();

            $refundMinor = (int) ($payment?->amount_minor ?? 0);
            if ($refundMinor > 0) {
                $this->wallets->credit(
                    $user,
                    $refundMinor,
                    'premium_refund_full',
                    "premium-refund-full-{$payment?->id}-".now()->timestamp,
                    ['payment_id' => $payment?->id],
                    description: __('Full premium charge refund.'),
                    admin: $admin,
                );
            }

            if ($subscription->tier === FreelancerSubscriptionTier::Pro->value) {
                $subscription->forceFill([
                    'status' => FreelancerSubscriptionStatus::AdminSuspended->value,
                    'tier' => FreelancerSubscriptionTier::Free->value,
                    'admin_suspended_at' => now(),
                    'admin_suspended_by_id' => $admin->id,
                    'admin_suspension_reason' => (string) ($data['reason_notes'] ?? $data['reason_code']),
                    'renewal_date' => null,
                ])->save();
            }

            $this->logAction(PremiumPatrolSubjectType::PremiumUser, $user->id, PremiumPatrolActionType::RefundPremium, $admin, $data, [
                'refund_minor' => $refundMinor,
            ]);

            $user->notify(new FreelancerProRefundedNotification($refundMinor, (string) ($data['reason_notes'] ?? $data['reason_code'])));

            return $subscription->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function grantPremium(User $target, User $admin, array $data): FreelancerSubscription
    {
        return DB::transaction(function () use ($target, $admin, $data): FreelancerSubscription {
            $subscription = $this->proService->subscriptionFor($target);
            $cycle = SubscriptionBillingCycle::from((string) ($data['billing_cycle'] ?? 'month'));
            $renewal = $cycle === SubscriptionBillingCycle::Year ? now()->addYear() : now()->addMonth();
            $pricing = \App\Support\PlatformSettings::freelancerProPricing();

            $subscription->forceFill([
                'status' => FreelancerSubscriptionStatus::Active->value,
                'tier' => FreelancerSubscriptionTier::Pro->value,
                'started_at' => now(),
                'renewal_date' => $renewal,
                'billing_cycle' => $cycle->value,
                'monthly_price_minor' => $pricing['monthly_minor'],
                'annual_price_minor' => $pricing['annual_minor'],
            ])->save();

            $this->logAction(PremiumPatrolSubjectType::PremiumUser, $target->id, PremiumPatrolActionType::GrantPremium, $admin, $data, [
                'billing_cycle' => $cycle->value,
            ]);

            $target->notify(new FreelancerProAdminGrantedNotification($subscription, (string) ($data['reason_notes'] ?? '')));

            return $subscription->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function demoteBoost(QuestBoost $boost, User $admin, array $data): QuestBoost
    {
        return DB::transaction(function () use ($boost, $admin, $data): QuestBoost {
            if ($boost->status === QuestBoostStatus::Active->value && $boost->isActive()) {
                $boost->forceFill([
                    'status' => QuestBoostStatus::ManuallyEndedEarly->value,
                    'actual_ended_at' => now(),
                ])->save();
            }

            $refundMinor = (int) $boost->planned_cost_minor;
            $client = $boost->client;
            if ($client && $refundMinor > 0) {
                $this->wallets->credit(
                    $client,
                    $refundMinor,
                    'boost_refund_demote',
                    "boost-demote-refund-{$boost->id}",
                    ['quest_boost_id' => $boost->id],
                    questId: $boost->quest_id,
                    description: __('Quest boost refund after demotion.'),
                    admin: $admin,
                );
            }

            $this->logAction(PremiumPatrolSubjectType::BoostedQuest, $boost->id, PremiumPatrolActionType::DemoteBoost, $admin, $data, [
                'refund_minor' => $refundMinor,
            ]);

            $client?->notify(new QuestBoostDemotedNotification($boost, (string) ($data['reason_notes'] ?? $data['reason_code'])));

            return $boost->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function refundBoost(QuestBoost $boost, User $admin, array $data): QuestBoost
    {
        return DB::transaction(function () use ($boost, $admin, $data): QuestBoost {
            $refundMinor = (int) $boost->planned_cost_minor;
            $client = $boost->client;
            if ($client && $refundMinor > 0) {
                $this->wallets->credit(
                    $client,
                    $refundMinor,
                    'boost_refund_only',
                    "boost-refund-only-{$boost->id}-".now()->timestamp,
                    ['quest_boost_id' => $boost->id],
                    questId: $boost->quest_id,
                    description: __('Quest boost fee refund.'),
                    admin: $admin,
                );
            }

            $this->logAction(PremiumPatrolSubjectType::BoostedQuest, $boost->id, PremiumPatrolActionType::RefundBoost, $admin, $data, [
                'refund_minor' => $refundMinor,
            ]);

            $client?->notify(new QuestBoostRefundedNotification($boost, $refundMinor, (string) ($data['reason_notes'] ?? $data['reason_code'])));

            return $boost->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function suspendQuest(Quest $quest, User $admin, array $data): Quest
    {
        $quest->forceFill([
            'admin_status' => AdminQuestStatus::Suspended->value,
            'admin_status_reason' => (string) ($data['reason_notes'] ?? $data['reason_code']),
            'admin_status_changed_by' => $admin->id,
            'admin_status_changed_at' => now(),
            'status' => QuestStatus::Closed->value,
        ])->save();

        $boostId = QuestBoost::query()->where('quest_id', $quest->id)->activeNow()->value('id') ?? 0;

        $this->logAction(PremiumPatrolSubjectType::BoostedQuest, $boostId, PremiumPatrolActionType::SuspendQuest, $admin, $data, [
            'quest_id' => $quest->id,
        ]);

        $quest->client?->notify(new QuestSuspendedByAdminNotification($quest, (string) ($data['reason_notes'] ?? $data['reason_code'])));

        return $quest->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function openInvestigation(string $subjectType, int $subjectId, User $admin, array $data): PremiumPatrolInvestigation
    {
        $case = PremiumPatrolInvestigation::query()->create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'status' => 'pending',
            'opened_by_id' => $admin->id,
            'assigned_to_id' => $data['assign_to_id'] ?? $admin->id,
            'title' => (string) ($data['title'] ?? 'Investigation case'),
            'timeline' => [[
                'at' => now()->toIso8601String(),
                'actor_id' => $admin->id,
                'note' => (string) ($data['reason_notes'] ?? 'Investigation opened.'),
            ]],
            'meta' => $data['meta'] ?? [],
        ]);

        $this->logAction(
            PremiumPatrolSubjectType::from($subjectType),
            $subjectId,
            PremiumPatrolActionType::Investigate,
            $admin,
            $data,
            ['case_id' => $case->id],
        );

        return $case;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addToWatchlist(User $target, User $admin, string $type, array $data): PremiumPatrolWatchlist
    {
        $days = (int) config('premium_patrol.watchlist_default_days', 90);
        $entry = PremiumPatrolWatchlist::query()->create([
            'watchlist_type' => $type,
            'user_id' => $target->id,
            'reason' => (string) ($data['reason_notes'] ?? $data['reason_code']),
            'added_by_id' => $admin->id,
            'expires_at' => now()->addDays($days),
            'meta' => $data['meta'] ?? [],
        ]);

        $this->logAction(PremiumPatrolSubjectType::PremiumUser, $target->id, PremiumPatrolActionType::AddWatchlist, $admin, $data, [
            'watchlist_type' => $type,
            'expires_at' => $entry->expires_at->toIso8601String(),
        ]);

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function flagManualReview(User $user, User $admin, array $data): FreelancerSubscription
    {
        $subscription = $this->proService->subscriptionFor($user);
        $subscription->forceFill([
            'manual_review_until' => now()->addDays(30),
        ])->save();

        $this->logAction(PremiumPatrolSubjectType::PremiumUser, $user->id, PremiumPatrolActionType::FlagManualReview, $admin, $data);

        return $subscription->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestClientVerification(QuestBoost $boost, User $admin, array $data): void
    {
        $client = $boost->client;
        if (! $client) {
            throw ValidationException::withMessages(['boost' => [__('Client not found.')]]);
        }

        $this->logAction(PremiumPatrolSubjectType::BoostedQuest, $boost->id, PremiumPatrolActionType::RequestVerification, $admin, $data);

        $client->notify(new QuestBoostVerificationRequestNotification(
            $boost,
            (string) ($data['message'] ?? ''),
            (int) config('premium_patrol.verification_response_hours', 48),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function grantBoost(array $data, User $admin): QuestBoost
    {
        return $this->boostService->grant($data, $admin);
    }

    public function dismissFlag(PremiumPatrolFlag $flag, User $admin, string $reason): PremiumPatrolFlag
    {
        $flag->forceFill([
            'status' => PremiumPatrolFlagStatus::Dismissed->value,
            'dismissed_at' => now(),
            'dismissed_by_id' => $admin->id,
            'dismissal_reason' => $reason,
        ])->save();

        return $flag->fresh();
    }

    private function proratedRefundMinor(FreelancerSubscription $subscription): int
    {
        if ($subscription->billing_cycle !== SubscriptionBillingCycle::Month->value || ! $subscription->renewal_date) {
            return 0;
        }

        $totalDays = 30;
        $remaining = max(0, (int) now()->diffInDays($subscription->renewal_date, false));
        $monthly = (int) $subscription->monthly_price_minor;

        return (int) round(($remaining / $totalDays) * $monthly);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    private function logAction(
        PremiumPatrolSubjectType $subjectType,
        int $subjectId,
        PremiumPatrolActionType $action,
        User $admin,
        array $data,
        array $meta = [],
    ): void {
        PremiumPatrolAction::query()->create([
            'subject_type' => $subjectType->value,
            'subject_id' => $subjectId,
            'action_type' => $action->value,
            'actor_id' => $admin->id,
            'reason_code' => $data['reason_code'] ?? null,
            'reason_notes' => $data['reason_notes'] ?? null,
            'meta' => array_merge($meta, ['input' => collect($data)->except(['_token'])->all()]),
            'occurred_at' => now(),
        ]);
    }
}
