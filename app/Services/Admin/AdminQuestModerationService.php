<?php

namespace App\Services\Admin;

use App\Enums\AdminQuestStatus;
use App\Models\AdminQuestNote;
use App\Models\AdminQuestNotice;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\AdminQuestModerationNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class AdminQuestModerationService
{
    public function __construct(
        private readonly AdminActivityLogger $activity,
    ) {}

    /**
     * @return list<array{value: string, label: string, tone: string}>
     */
    public function statusOptions(): array
    {
        return array_map(fn (AdminQuestStatus $status) => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $status->tone(),
        ], AdminQuestStatus::cases());
    }

    public function statusPayload(null|string|AdminQuestStatus $status): array
    {
        $enum = $status instanceof AdminQuestStatus
            ? $status
            : AdminQuestStatus::tryFrom((string) ($status ?: AdminQuestStatus::Clear->value)) ?? AdminQuestStatus::Clear;

        return [
            'value' => $enum->value,
            'label' => $enum->label(),
            'tone' => $enum->tone(),
        ];
    }

    public function changeStatus(Quest $quest, User $admin, array $data, Request $request): Quest
    {
        $to = AdminQuestStatus::tryFrom((string) ($data['admin_status'] ?? ''));
        if (! $to) {
            throw ValidationException::withMessages(['admin_status' => __('Choose a supported admin status.')]);
        }

        $from = $this->statusPayload($quest->admin_status);
        $minimum = $to === AdminQuestStatus::Suspended ? 50 : 20;
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < $minimum) {
            throw ValidationException::withMessages([
                'reason' => __('The reason must be at least :count characters.', ['count' => $minimum]),
            ]);
        }

        $quest->forceFill([
            'admin_status' => $to,
            'admin_status_reason' => $reason,
            'admin_status_changed_by' => $admin->id,
            'admin_status_changed_at' => now(),
        ])->save();

        Cache::forget('admin.quest-engine.summary');

        $this->activity->log($admin, 'admin.quest.admin_status_changed', Quest::class, $quest->id, [
            'from' => $from,
            'to' => $this->statusPayload($to),
            'reason' => $reason,
            'notify_client' => (bool) ($data['notify_client'] ?? false),
            'notification_preview' => $data['notification_preview'] ?? null,
            'referred_to_admin_id' => $data['referred_to_admin_id'] ?? null,
        ], $request);

        if ((bool) ($data['notify_client'] ?? false) && $quest->client) {
            $quest->client->notify(new AdminQuestModerationNotification(
                $quest,
                __('HustleSafe moderation update: :status', ['status' => $to->label()]),
                (string) ($data['notification_preview'] ?? $reason),
                'quest_admin_status_changed',
            ));
        }

        return $quest->refresh();
    }

    public function createNotice(Quest $quest, User $admin, array $data, Request $request): AdminQuestNotice
    {
        $notice = AdminQuestNotice::query()->create([
            'quest_id' => $quest->id,
            'created_by_admin_id' => $admin->id,
            'type' => $data['type'],
            'body' => $data['body'],
            'visible_to_users' => (bool) ($data['visible_to_users'] ?? true),
        ]);

        $this->activity->log($admin, 'admin.quest.notice_created', Quest::class, $quest->id, [
            'notice_id' => $notice->id,
            'type' => $notice->type,
            'visible_to_users' => $notice->visible_to_users,
            'notify_stakeholders' => (bool) ($data['notify_stakeholders'] ?? false),
        ], $request);

        if ((bool) ($data['notify_stakeholders'] ?? false)) {
            $this->notifyStakeholders(
                $quest,
                __('Quest notice: :type', ['type' => str($notice->type)->headline()]),
                $notice->body,
                'quest_admin_notice',
            );
        }

        return $notice->load('creator:id,name,email');
    }

    public function createNote(Quest $quest, User $admin, array $data, Request $request): AdminQuestNote
    {
        $note = AdminQuestNote::query()->create([
            'quest_id' => $quest->id,
            'admin_id' => $admin->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
        ]);

        $this->activity->log($admin, 'admin.quest.note_created', Quest::class, $quest->id, [
            'note_id' => $note->id,
            'is_pinned' => $note->is_pinned,
            'parent_id' => $note->parent_id,
        ], $request);

        return $note->load('admin:id,name,email,avatar_url');
    }

    private function notifyStakeholders(Quest $quest, string $title, string $body, string $kind): void
    {
        $quest->loadMissing('client');
        $recipients = collect([$quest->client])->filter();

        $freelancers = QuestOffer::query()
            ->with('freelancer')
            ->where('quest_id', $quest->id)
            ->get()
            ->pluck('freelancer')
            ->filter();

        $recipients
            ->merge($freelancers)
            ->unique('id')
            ->each(fn (User $user) => $user->notify(new AdminQuestModerationNotification($quest, $title, $body, $kind)));
    }
}
