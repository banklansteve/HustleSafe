<?php

namespace App\Services\Operations;

use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\QuestFile;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\Admin\QuestManagementEngineService;
use App\Services\AdminActivityLogger;
use App\Services\QuestCoverService;
use App\Services\QuestFileStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class StaffQuestModerationService
{
    public function __construct(
        private readonly QuestManagementEngineService $engine,
        private readonly AdminActivityLogger $logger,
        private readonly StaffSupportMessagingService $support,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function updateQuest(Quest $quest, User $staff, array $data, Request $request): array
    {
        if ((bool) ($data['submit_for_approval'] ?? false)) {
            return $this->submitEditForApproval($quest, $staff, $data, $request);
        }

        $payload = $this->staffEditPayload($quest, $data);
        $this->engine->updateQuest($quest, $staff, $payload, $request);

        $this->logger->log($staff, 'operations.quest.updated', Quest::class, $quest->id, [
            'fields' => array_keys($payload),
        ], $request);

        return [
            'message' => __('Quest updated and the client can be notified from the audit trail.'),
            'quest' => $this->engine->detail($quest->refresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function submitEditForApproval(Quest $quest, User $staff, array $data, Request $request): array
    {
        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 20) {
            throw ValidationException::withMessages([
                'reason' => __('Explain the proposed edit in at least 20 characters.'),
            ]);
        }

        $superAdmin = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first();

        if ($superAdmin === null) {
            throw ValidationException::withMessages([
                'submit_for_approval' => __('No Super Admin is available to review this edit right now.'),
            ]);
        }

        $proposed = collect($this->staffEditPayload($quest, $data))
            ->except(['budget_amount', 'visibility', 'project_type', 'start_timing', 'scheduled_start_date', 'estimated_completion_days', 'due_at'])
            ->all();

        $task = AdminTask::query()->create([
            'created_by_admin_id' => $staff->id,
            'assigned_to_admin_id' => $superAdmin->id,
            'source_type' => Quest::class,
            'source_id' => $quest->id,
            'title' => 'Quest edit approval · '.$quest->reference_code,
            'description' => $reason."\n\nProposed changes:\n".json_encode($proposed, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'priority' => 'medium',
            'status' => 'open',
            'due_at' => now()->addDay(),
        ]);

        $this->logger->log($staff, 'operations.quest.edit_submitted_for_approval', Quest::class, $quest->id, [
            'task_id' => $task->id,
            'assigned_to' => $superAdmin->id,
            'proposed' => $proposed,
        ], $request);

        return [
            'message' => __('Edit submitted for Super Admin approval. The live Quest is unchanged until approved.'),
            'task_id' => $task->id,
            'quest' => $this->engine->detail($quest),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function removeFile(Quest $quest, QuestFile $file, User $staff, array $data, Request $request): array
    {
        if ((int) $file->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $reason = trim((string) ($data['reason'] ?? ''));
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'reason' => __('Provide at least 10 characters explaining why this media is being removed.'),
            ]);
        }

        $name = $file->original_name;
        app(QuestFileStorageService::class)->delete($file);
        app(QuestCoverService::class)->sync($quest->fresh(['files']));

        $this->logger->log($staff, 'operations.quest.file_removed', Quest::class, $quest->id, [
            'file_id' => $file->id,
            'file_name' => $name,
            'reason' => $reason,
        ], $request);

        return [
            'message' => __('Media removed from the Quest.'),
            'quest' => $this->engine->detail($quest->refresh()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function contactStakeholder(Quest $quest, User $staff, array $data, Request $request): array
    {
        $recipient = match ($data['recipient'] ?? 'client') {
            'freelancer' => $this->resolveFreelancerRecipient($quest, $data),
            default => $quest->client,
        };

        if ($recipient === null) {
            throw ValidationException::withMessages([
                'recipient' => __('This Quest does not have a contactable user for that role yet.'),
            ]);
        }

        $this->sendContact($recipient, $staff, $data, $request, Quest::class, $quest->id);

        if ((bool) ($data['open_cs_ticket'] ?? false) && $this->support->supportTablesReady()) {
            $this->support->createTicket($staff, [
                'user_id' => $recipient->id,
                'subject' => (string) ($data['subject'] ?? 'Support follow-up'),
                'description' => (string) ($data['body'] ?? ''),
                'category' => 'general',
                'priority' => 'medium',
            ]);
        }

        return [
            'message' => __('Message sent to :name.', ['name' => $recipient->name]),
            'quest' => $this->engine->detail($quest->refresh()),
        ];
    }

    /**
     * @param  class-string  $subjectType
     */
    public function contactUser(User $recipient, User $staff, array $data, Request $request, string $subjectType, int $subjectId): void
    {
        $this->sendContact($recipient, $staff, $data, $request, $subjectType, $subjectId);

        if ((bool) ($data['open_cs_ticket'] ?? false) && $this->support->supportTablesReady()) {
            $this->support->createTicket($staff, [
                'user_id' => $recipient->id,
                'subject' => (string) ($data['subject'] ?? 'Support follow-up'),
                'description' => (string) ($data['body'] ?? ''),
                'category' => 'general',
                'priority' => 'medium',
            ]);
        }
    }

    /**
     * @param  class-string  $subjectType
     */
    private function sendContact(User $recipient, User $staff, array $data, Request $request, string $subjectType, int $subjectId): void
    {
        $subject = trim((string) ($data['subject'] ?? 'Message from HustleSafe Support'));
        $body = trim((string) ($data['body'] ?? ''));
        if (mb_strlen($body) < 10) {
            throw ValidationException::withMessages([
                'body' => __('Write at least 10 characters for the message body.'),
            ]);
        }

        $channel = (string) ($data['channel'] ?? 'both');

        if (in_array($channel, ['email', 'both'], true)) {
            Mail::raw($body, function ($mail) use ($recipient, $subject): void {
                $mail->to($recipient->email)->subject($subject);
            });
        }

        if (in_array($channel, ['in_app', 'both'], true)) {
            $recipient->notify(new AdminUserMessageNotification($subject, $body));
        }

        $this->logger->log($staff, 'operations.user.contacted', $subjectType, $subjectId, [
            'recipient_id' => $recipient->id,
            'recipient_email' => $recipient->email,
            'channel' => $channel,
            'subject' => $subject,
        ], $request);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveFreelancerRecipient(Quest $quest, array $data): ?User
    {
        $freelancerId = (int) ($data['freelancer_id'] ?? 0);
        if ($freelancerId > 0) {
            return User::query()->find($freelancerId);
        }

        return $quest->freelancer ?? $quest->acceptedOffer?->freelancer;
    }

    /**
     * @return array<string, mixed>
     */
    private function staffEditPayload(Quest $quest, array $data): array
    {
        return [
            'title' => $data['title'] ?? $quest->title,
            'description' => $data['description'] ?? $quest->description,
            'quest_category_id' => $data['quest_category_id'] ?? $quest->quest_category_id,
            'budget_amount' => ((int) $quest->budget_amount_minor) / 100,
            'max_offers' => $data['max_offers'] ?? $quest->max_offers,
            'city' => $data['city'] ?? $quest->city,
            'state_id' => $data['state_id'] ?? $quest->state_id,
            'visibility' => $quest->visibility?->value ?? (string) $quest->visibility,
            'project_type' => $quest->project_type?->value ?? (string) $quest->project_type,
            'start_timing' => $quest->start_timing?->value ?? (string) $quest->start_timing,
            'scheduled_start_date' => $quest->scheduled_start_date?->toDateString(),
            'estimated_completion_days' => $quest->estimated_completion_days,
            'due_at' => $quest->due_at?->toDateString(),
            'reason' => $data['reason'] ?? '',
            'notify_client' => (bool) ($data['notify_client'] ?? true),
            'notification_preview' => $data['notification_preview'] ?? null,
        ];
    }
}
