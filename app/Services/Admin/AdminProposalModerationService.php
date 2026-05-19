<?php

namespace App\Services\Admin;

use App\Enums\AdminProposalStatus;
use App\Models\AdminProposalFlag;
use App\Models\AdminProposalNote;
use App\Models\AdminProposalNotice;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminProposalModerationService
{
    public function __construct(
        private readonly AdminActivityLogger $activity,
    ) {}

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public function statusOptions(): array
    {
        return array_map(fn (AdminProposalStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ], AdminProposalStatus::cases());
    }

    public function statusPayload(null|string|AdminProposalStatus $status): array
    {
        $enum = $status instanceof AdminProposalStatus
            ? $status
            : AdminProposalStatus::tryFrom((string) ($status ?: AdminProposalStatus::Clear->value)) ?? AdminProposalStatus::Clear;

        return [
            'value' => $enum->value,
            'label' => $enum->label(),
            'tone' => $enum->tone(),
        ];
    }

    public function changeStatus(QuestOffer $proposal, User $admin, array $data, Request $request): QuestOffer
    {
        if (! Schema::hasColumn('quest_offers', 'admin_status')) {
            throw ValidationException::withMessages(['admin_status' => __('Run the proposal moderation migration before changing proposal admin status.')]);
        }

        $to = AdminProposalStatus::tryFrom((string) ($data['admin_status'] ?? ''));
        if (! $to) {
            throw ValidationException::withMessages(['admin_status' => __('Choose a supported admin status.')]);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        $minimum = $to === AdminProposalStatus::Suspended ? 50 : 20;
        if (mb_strlen($reason) < $minimum) {
            throw ValidationException::withMessages([
                'reason' => __('The reason must be at least :count characters.', ['count' => $minimum]),
            ]);
        }

        $from = $this->statusPayload($proposal->admin_status);
        $proposal->forceFill([
            'admin_status' => $to,
            'admin_status_reason' => $reason,
            'admin_status_changed_by' => $admin->id,
            'admin_status_changed_at' => now(),
            'admin_notice_severity' => in_array($to, [AdminProposalStatus::ActionRequired, AdminProposalStatus::Restricted, AdminProposalStatus::Suspended], true)
                ? $to->value
                : null,
        ])->save();

        Cache::forget('admin.proposal-engine.summary');

        $this->activity->log($admin, 'admin.proposal.admin_status_changed', QuestOffer::class, $proposal->id, [
            'from' => $from,
            'to' => $this->statusPayload($to),
            'operational_status_unchanged' => $proposal->status,
            'reason' => $reason,
            'notify_freelancer' => (bool) ($data['notify_freelancer'] ?? false),
            'notify_client' => (bool) ($data['notify_client'] ?? false),
            'notification_preview' => $data['notification_preview'] ?? null,
            'referred_to_admin_id' => $data['referred_to_admin_id'] ?? null,
        ], $request);

        return $proposal->refresh();
    }

    public function createFlag(QuestOffer $proposal, User $admin, array $data, Request $request): AdminProposalFlag
    {
        if (! Schema::hasTable('admin_proposal_flags')) {
            throw ValidationException::withMessages(['description' => __('Proposal flagging is unavailable until migrations have run.')]);
        }

        if (($data['visibility_impact'] ?? 'none') === 'hide_pending_resolution' && mb_strlen((string) $data['description']) < 50) {
            throw ValidationException::withMessages(['description' => __('Suspending a proposal requires at least 50 characters of context.')]);
        }

        $flag = AdminProposalFlag::query()->create([
            'quest_offer_id' => $proposal->id,
            'created_by_admin_id' => $admin->id,
            'assigned_to_admin_id' => $data['assigned_to_admin_id'] ?? null,
            'assigned_group' => $data['assigned_group'] ?? null,
            'type' => $data['type'],
            'priority' => $data['priority'],
            'description' => $data['description'],
            'visibility_impact' => $data['visibility_impact'] ?? 'none',
            'due_at' => $data['due_at'] ?? null,
            'status' => 'open',
        ]);

        Cache::forget('admin.proposal-engine.summary');

        $this->activity->log($admin, 'admin.proposal.flag_created', QuestOffer::class, $proposal->id, [
            'flag_id' => $flag->id,
            'type' => $flag->type,
            'priority' => $flag->priority,
            'description' => $flag->description,
            'visibility_impact' => $flag->visibility_impact,
            'assigned_to_admin_id' => $flag->assigned_to_admin_id,
            'assigned_group' => $flag->assigned_group,
            'due_at' => $flag->due_at?->toDateString(),
            'notify_freelancer' => (bool) ($data['notify_freelancer'] ?? false),
            'notify_client' => (bool) ($data['notify_client'] ?? false),
        ], $request);

        $impact = $data['visibility_impact'] ?? 'none';
        if ($impact === 'hide_pending_resolution') {
            $this->changeStatus($proposal, $admin, [
                'admin_status' => AdminProposalStatus::Suspended->value,
                'reason' => $data['description'],
            ], $request);
        } elseif ($impact === 'restrict_acceptance') {
            $this->changeStatus($proposal, $admin, [
                'admin_status' => AdminProposalStatus::Restricted->value,
                'reason' => $data['description'],
            ], $request);
        } elseif (($proposal->admin_status?->value ?? (string) $proposal->admin_status) === AdminProposalStatus::Clear->value) {
            $this->changeStatus($proposal, $admin, [
                'admin_status' => AdminProposalStatus::Flagged->value,
                'reason' => $data['description'],
            ], $request);
        }

        return $flag->load(['creator:id,name,email', 'assignee:id,name,email']);
    }

    public function resolveFlag(AdminProposalFlag $flag, User $admin, array $data, Request $request): AdminProposalFlag
    {
        $flag->forceFill([
            'status' => 'resolved',
            'resolution_outcome' => $data['resolution_outcome'],
            'resolution_note' => $data['resolution_note'],
            'resolved_by_admin_id' => $admin->id,
            'resolved_at' => now(),
        ])->save();

        Cache::forget('admin.proposal-engine.summary');

        $this->activity->log($admin, 'admin.proposal.flag_resolved', QuestOffer::class, $flag->quest_offer_id, [
            'flag_id' => $flag->id,
            'resolution_outcome' => $flag->resolution_outcome,
            'resolution_note' => $flag->resolution_note,
        ], $request);

        return $flag->load(['creator:id,name,email', 'assignee:id,name,email', 'resolver:id,name,email']);
    }

    public function createNotice(QuestOffer $proposal, User $admin, array $data, Request $request): AdminProposalNotice
    {
        if (! Schema::hasTable('admin_proposal_notices')) {
            throw ValidationException::withMessages(['body' => __('Run the proposal moderation migration before posting proposal notices.')]);
        }

        $notice = AdminProposalNotice::query()->create([
            'quest_offer_id' => $proposal->id,
            'created_by_admin_id' => $admin->id,
            'type' => $data['type'],
            'body' => $data['body'],
            'visible_to_freelancer' => (bool) ($data['visible_to_freelancer'] ?? true),
            'visible_to_client' => (bool) ($data['visible_to_client'] ?? true),
        ]);

        $proposal->forceFill(['admin_notice_severity' => $notice->type])->save();

        $this->activity->log($admin, 'admin.proposal.notice_created', QuestOffer::class, $proposal->id, [
            'notice_id' => $notice->id,
            'type' => $notice->type,
            'visible_to_freelancer' => $notice->visible_to_freelancer,
            'visible_to_client' => $notice->visible_to_client,
            'notify_stakeholders' => (bool) ($data['notify_stakeholders'] ?? false),
        ], $request);

        return $notice->load('creator:id,name,email');
    }

    public function createNote(QuestOffer $proposal, User $admin, array $data, Request $request): AdminProposalNote
    {
        if (! Schema::hasTable('admin_proposal_notes')) {
            throw ValidationException::withMessages(['body' => __('Run the proposal moderation migration before saving proposal notes.')]);
        }

        $note = AdminProposalNote::query()->create([
            'quest_offer_id' => $proposal->id,
            'admin_id' => $admin->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ]);

        $this->activity->log($admin, 'admin.proposal.note_created', QuestOffer::class, $proposal->id, [
            'note_id' => $note->id,
            'is_pinned' => $note->is_pinned,
            'parent_id' => $note->parent_id,
        ], $request);

        return $note->load('admin:id,name,email,avatar_url');
    }
}
