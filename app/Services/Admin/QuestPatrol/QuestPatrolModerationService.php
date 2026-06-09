<?php

namespace App\Services\Admin\QuestPatrol;

use App\Enums\AdminQuestStatus;
use App\Enums\QuestPatrolFlagStatus;
use App\Enums\QuestPatrolSubjectType;
use App\Models\ModerationApprovalRequest;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\QuestPatrolFlag;
use App\Models\User;
use App\Enums\QuestStatus;
use App\Services\Admin\AdminQuestModerationService;
use App\Services\Admin\PromotionsGrowthService;
use App\Services\Admin\QuestBoostService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class QuestPatrolModerationService
{
    public function __construct(
        private readonly QuestBoostService $boostService,
        private readonly AdminQuestModerationService $questModeration,
        private readonly QuestPatrolAnomalyService $anomalies,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function adminBoost(Quest $quest, User $admin, array $data): array
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $boost = $this->boostService->grant([
            'quest_id' => $quest->id,
            'tier' => $data['tier'],
            'grant_reason' => ($data['free'] ?? false ? '[Admin promotion] ' : '').($data['reason_label'] ?? $data['reason_code'] ?? 'Admin boost'),
        ], $admin);

        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'admin_boost', $admin, $data, ['boost_id' => $boost->id]);

        return ['boost_id' => $boost->id, 'message' => 'Quest boosted successfully.'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestRevision(Quest $quest, User $admin, array $data, Request $request): array
    {
        $issue = (string) ($data['issue_type'] ?? 'other');
        $message = (string) ($data['message'] ?? '');
        $deadlineDays = max(1, (int) ($data['deadline_days'] ?? 7));
        $reason = 'Revision requested ('.$issue.'): '.$message;
        if (mb_strlen($reason) < 20) {
            $reason = str_pad($reason, 20, '.');
        }

        $this->questModeration->changeStatus($quest, $admin, [
            'admin_status' => AdminQuestStatus::ActionRequired->value,
            'reason' => $reason,
            'notify_client' => true,
            'notification_preview' => $message !== ''
                ? $message
                : 'Your quest description needs clarification before we can feature it further. Please update within '.$deadlineDays.' days.',
        ], $request);

        $this->questModeration->createNotice($quest, $admin, [
            'type' => 'warning',
            'body' => $message !== ''
                ? $message
                : 'Your quest description needs clarification before we can feature it further. Please update the quest within '.$deadlineDays.' days.',
            'visible_to_users' => true,
            'notify_stakeholders' => true,
        ], $request);

        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'request_revision', $admin, $data);

        return ['message' => 'Revision request sent to client.'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pauseQuest(Quest $quest, User $admin, array $data): array
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $hours = min(72, max(24, (int) ($data['hours'] ?? 48)));
        $quest->forceFill([
            'admin_status' => AdminQuestStatus::Suspended->value,
            'admin_status_reason' => (string) ($data['reason'] ?? 'Paused by super admin for patrol review'),
            'admin_status_changed_by' => $admin->id,
            'admin_status_changed_at' => now(),
            'listing_expires_at' => now()->addHours($hours),
        ])->save();

        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'pause_quest', $admin, $data, ['hours' => $hours]);

        return ['message' => "Quest paused from promotion for {$hours} hours."];
    }

    public function dismissFlag(QuestPatrolFlag $flag, User $admin, array $data): QuestPatrolFlag
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $flag->forceFill([
            'status' => QuestPatrolFlagStatus::Dismissed->value,
            'dismissed_at' => now(),
            'dismissed_by_id' => $admin->id,
            'dismissal_reason_code' => $data['reason_code'] ?? null,
            'dismissal_reason' => $data['reason_notes'] ?? $data['reason'] ?? null,
        ])->save();

        return $flag->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rateProposal(QuestOffer $proposal, User $admin, array $data): QuestOffer
    {
        $rating = max(1, min(4, (int) ($data['rating'] ?? 0)));
        if ($rating < 1) {
            throw ValidationException::withMessages(['rating' => ['Select a quality rating.']]);
        }

        $proposal->forceFill(['admin_quality_rating' => $rating])->save();
        $this->logAction(QuestPatrolSubjectType::Proposal, $proposal->id, 'rate_proposal', $admin, $data);

        return $proposal->fresh();
    }

    public function recommendProposal(QuestOffer $proposal, User $admin): QuestOffer
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $proposal->forceFill([
            'admin_recommended_at' => now(),
            'admin_recommended_by_id' => $admin->id,
        ])->save();

        $this->logAction(QuestPatrolSubjectType::Proposal, $proposal->id, 'recommend_proposal', $admin, []);

        return $proposal->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function requestClarification(QuestOffer $proposal, User $admin, array $data): array
    {
        $this->logAction(QuestPatrolSubjectType::Proposal, $proposal->id, 'request_clarification', $admin, $data);

        return [
            'message' => 'Clarification request logged. Notify freelancer via contact panel.',
            'deadline_hours' => (int) ($data['deadline_hours'] ?? 48),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function hideProposalRequest(QuestOffer $proposal, User $admin, array $data): array
    {
        if ($admin->role?->slug === 'super_admin') {
            $proposal->forceFill(['admin_hidden_from_client' => true])->save();
            $this->logAction(QuestPatrolSubjectType::Proposal, $proposal->id, 'hide_proposal', $admin, $data);

            return ['message' => 'Proposal hidden from client view.', 'pending_approval' => false];
        }

        ModerationApprovalRequest::query()->create([
            'request_type' => 'hide_proposal',
            'subject_type' => 'proposal',
            'subject_id' => $proposal->id,
            'requested_by_id' => $admin->id,
            'reason' => (string) ($data['reason'] ?? ''),
            'status' => 'pending',
            'meta' => ['proposal_id' => $proposal->id, 'quest_id' => $proposal->quest_id],
        ]);

        $this->logAction(QuestPatrolSubjectType::Proposal, $proposal->id, 'hide_proposal_request', $admin, $data);

        return ['message' => 'Hide request submitted for super admin approval.', 'pending_approval' => true];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function featureQuest(Quest $quest, User $admin, array $data): array
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $listing = app(PromotionsGrowthService::class)->grantFeatured([
            'quest_id' => $quest->id,
            'tier' => $data['tier'] ?? 'standard',
            'duration_days' => (int) ($data['duration_days'] ?? 7),
            'amount_paid_minor' => 0,
            'manual_grant_reason' => '[Moderation curation] '.($data['reason'] ?? 'Featured from patrol'),
        ], $admin);

        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'feature_quest', $admin, $data, ['listing_id' => $listing->id]);

        return ['message' => 'Quest featured on homepage section.', 'listing_id' => $listing->id];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function verifyDeliverables(Quest $quest, User $admin, array $data, Request $request): array
    {
        $verdict = (string) ($data['verdict'] ?? 'verified');
        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'verify_deliverables', $admin, $data, ['checklist' => $data['checklist'] ?? []]);

        if ($verdict === 'issues_found' || $verdict === 'needs_clarification') {
            $this->requestRevision($quest, $admin, [
                'issue_type' => 'missing_deliverables',
                'message' => (string) ($data['notes'] ?? 'Deliverables need clarification before this quest can proceed.'),
                'deadline_days' => (int) ($data['deadline_days'] ?? 7),
            ], $request);
        }

        return ['message' => 'Deliverables check recorded.', 'verdict' => $verdict];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function mergeDuplicate(Quest $quest, User $admin, array $data, Request $request): array
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $originalId = (int) ($data['original_quest_id'] ?? 0);
        $original = Quest::query()->findOrFail($originalId);
        $reason = 'Marked as duplicate of quest #'.$original->reference_code.' (ID '.$original->id.').';

        $quest->forceFill([
            'status' => QuestStatus::ClosedUnawarded->value,
            'admin_status' => AdminQuestStatus::Resolved->value,
            'admin_status_reason' => $reason,
            'admin_status_changed_by' => $admin->id,
            'admin_status_changed_at' => now(),
        ])->save();

        $this->questModeration->createNotice($quest, $admin, [
            'type' => 'informational',
            'body' => 'This quest was closed as a duplicate. Please continue on '.$original->title.' ('.$original->reference_code.').',
            'visible_to_users' => true,
            'notify_stakeholders' => true,
        ], $request);

        $this->logAction(QuestPatrolSubjectType::Quest, $quest->id, 'merge_duplicate', $admin, $data, ['original_quest_id' => $original->id]);

        return ['message' => 'Duplicate quest closed and linked to original.', 'original_quest_id' => $original->id];
    }

    public function reviewApprovalRequest(ModerationApprovalRequest $approval, User $admin, array $data): ModerationApprovalRequest
    {
        if ($admin->role?->slug !== 'super_admin') {
            abort(403);
        }

        $decision = (string) ($data['decision'] ?? 'rejected');
        $approval->forceFill([
            'status' => $decision === 'approved' ? 'approved' : 'rejected',
            'reviewed_by_id' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $data['review_notes'] ?? null,
        ])->save();

        if ($decision === 'approved' && $approval->request_type === 'hide_proposal') {
            $proposal = QuestOffer::query()->find($approval->subject_id);
            $proposal?->forceFill(['admin_hidden_from_client' => true])->save();
        }

        return $approval->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $meta
     */
    private function logAction(QuestPatrolSubjectType $subjectType, int $subjectId, string $action, User $admin, array $data, array $meta = []): void
    {
        if (! DB::getSchemaBuilder()->hasTable('quest_patrol_actions')) {
            return;
        }

        DB::table('quest_patrol_actions')->insert([
            'subject_type' => $subjectType->value,
            'subject_id' => $subjectId,
            'action_type' => $action,
            'actor_id' => $admin->id,
            'reason_code' => $data['reason_code'] ?? null,
            'reason_notes' => $data['reason_notes'] ?? $data['reason'] ?? null,
            'meta' => json_encode(array_merge($meta, ['input' => collect($data)->except(['_token'])->all()])),
            'occurred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
