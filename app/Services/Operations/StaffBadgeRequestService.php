<?php

namespace App\Services\Operations;

use App\Models\PromotionBadge;
use App\Models\QuestDispute;
use App\Models\StaffBadgeRequest;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Validation\ValidationException;

class StaffBadgeRequestService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(): array
    {
        return [
            'badge_options' => config('operations_extended.manual_badge_slugs'),
            'items' => StaffBadgeRequest::query()
                ->with('user:id,name,email,trust_score,client_trust_score')
                ->latest()
                ->limit(100)
                ->get()
                ->map(fn (StaffBadgeRequest $r) => $this->row($r))
                ->all(),
        ];
    }

    public function detail(StaffBadgeRequest $request): array
    {
        $request->load('user.role');

        $user = $request->user;
        $metrics = $this->buildMetricsSnapshot($user);
        $disqualifiers = $this->disqualifiers($user);

        return [
            'request' => $this->row($request),
            'applicant_note' => $request->applicant_note,
            'metrics' => $request->metrics_snapshot ?? $metrics,
            'live_metrics' => $metrics,
            'disqualifiers' => $disqualifiers,
            'already_has_badge' => $user
                ? $user->promotionBadges()->where('slug', $request->badge_slug)->exists()
                : false,
        ];
    }

    public function approve(StaffBadgeRequest $request, User $staff, string $note): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Request already decided.']);
        }

        $badge = PromotionBadge::query()->where('slug', $request->badge_slug)->first();
        if (! $badge) {
            throw ValidationException::withMessages(['badge' => 'Badge not found.']);
        }

        $user = $request->user;
        if ($user) {
            $badge->users()->syncWithoutDetaching([
                $user->id => [
                    'justification' => $note,
                    'awarded_at' => now(),
                    'revoked_at' => null,
                ],
            ]);
            $user->notify(new AdminUserMessageNotification(
                'Badge request approved',
                $note,
            ));
        }

        $request->forceFill([
            'status' => 'approved',
            'reviewed_by_staff_id' => $staff->id,
            'decision_note' => $note,
            'reviewed_at' => now(),
        ])->save();

        $this->logger->log($staff, 'staff_badge_request.approved', StaffBadgeRequest::class, $request->id, []);
    }

    public function reject(StaffBadgeRequest $request, User $staff, string $note): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['request' => 'Request already decided.']);
        }

        $request->forceFill([
            'status' => 'rejected',
            'reviewed_by_staff_id' => $staff->id,
            'decision_note' => $note,
            'reviewed_at' => now(),
        ])->save();

        $request->user?->notify(new AdminUserMessageNotification(
            'Badge request update',
            $note,
        ));

        $this->logger->log($staff, 'staff_badge_request.rejected', StaffBadgeRequest::class, $request->id, []);
    }

    public function escalate(StaffBadgeRequest $request, User $staff, string $note): void
    {
        $request->forceFill([
            'status' => 'escalated',
            'escalated_to_super_admin' => true,
            'reviewed_by_staff_id' => $staff->id,
            'decision_note' => $note,
        ])->save();

        $this->logger->log($staff, 'staff_badge_request.escalated', StaffBadgeRequest::class, $request->id, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildMetricsSnapshot(?User $user): array
    {
        if (! $user) {
            return [];
        }

        return [
            'trust_score' => $user->trust_score ?? $user->client_trust_score,
            'completed_quests' => $user->questsAsFreelancer()->where('status', 'completed')->count(),
            'avg_rating' => $user->reviewsReceived()->avg('rating'),
            'kyc_tier' => $user->kyc_tier ?? $user->verification_tier,
        ];
    }

    /**
     * @return list<string>
     */
    private function disqualifiers(?User $user): array
    {
        if (! $user) {
            return ['User not found'];
        }

        $issues = [];
        $trust = (int) ($user->trust_score ?? $user->client_trust_score ?? 0);
        if ($trust < 50) {
            $issues[] = 'Trust score below threshold';
        }

        $openDisputes = QuestDispute::query()
            ->where(function ($q) use ($user): void {
                $q->whereHas('quest', fn ($sub) => $sub->where('freelancer_id', $user->id)->orWhere('client_id', $user->id));
            })
            ->whereIn('status', ['open', 'in_review', 'mediation'])
            ->count();

        if ($openDisputes > 0) {
            $issues[] = 'Active disputes on account';
        }

        return $issues;
    }

    private function row(StaffBadgeRequest $request): array
    {
        return [
            'id' => $request->id,
            'status' => $request->status,
            'badge_slug' => $request->badge_slug,
            'badge_label' => config('operations_extended.manual_badge_slugs')[$request->badge_slug] ?? $request->badge_slug,
            'user' => $request->user ? [
                'id' => $request->user->id,
                'name' => $request->user->name,
                'email' => $request->user->email,
                'trust_score' => $request->user->trust_score ?? $request->user->client_trust_score,
            ] : null,
            'escalated' => $request->escalated_to_super_admin,
            'created_at' => $request->created_at?->toIso8601String(),
        ];
    }
}
