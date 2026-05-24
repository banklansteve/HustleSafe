<?php

namespace App\Services\Operations;

use App\Enums\DisputeMessageKind;
use App\Enums\QuestDisputeStatus;
use App\Models\DisputeEvent;
use App\Models\DisputeMessage;
use App\Models\QuestDispute;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class StaffDisputeManagementService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(Request $request): LengthAwarePaginator
    {
        $queue = (string) $request->input('queue', 'open');
        $q = trim((string) $request->input('q', ''));

        $query = QuestDispute::query()
            ->with(['quest:id,title,reference_code', 'openedBy:id,name,email', 'assignedStaff:id,name,email']);

        $query = match ($queue) {
            'mine' => $query->where('assigned_staff_id', $request->user()->id),
            'unclaimed' => $query->whereNull('assigned_staff_id')->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn]),
            'tier_1' => $query->where('tier', 1)->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn]),
            'tier_2' => $query->where('tier', 2)->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn]),
            'awaiting_ruling' => $query->where('status', QuestDisputeStatus::AwaitingRuling),
            default => $query->whereNotIn('status', [QuestDisputeStatus::Resolved, QuestDisputeStatus::ClosedWithdrawn]),
        };

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('uuid', 'like', "%{$q}%")
                    ->orWhereHas('quest', fn ($quest) => $quest->where('title', 'like', "%{$q}%")->orWhere('reference_code', 'like', "%{$q}%"))
                    ->orWhereHas('openedBy', fn ($user) => $user->where('email', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            });
        }

        return $query->latest()->paginate(min(100, max(25, $request->integer('per_page', 50))))
            ->withQueryString()
            ->through(fn (QuestDispute $dispute) => $this->row($dispute));
    }

    public function queues(): array
    {
        return [
            ['key' => 'open', 'label' => 'All open', 'hint' => 'Full dispute queue'],
            ['key' => 'unclaimed', 'label' => 'Unclaimed', 'hint' => 'Pick up next case'],
            ['key' => 'mine', 'label' => 'My disputes', 'hint' => 'Assigned to you'],
            ['key' => 'tier_1', 'label' => 'Tier 1', 'hint' => 'Self-resolution'],
            ['key' => 'tier_2', 'label' => 'Tier 2', 'hint' => 'Mediation'],
            ['key' => 'awaiting_ruling', 'label' => 'Awaiting ruling', 'hint' => 'Ready for decision'],
        ];
    }

    public function detail(QuestDispute $dispute): array
    {
        $dispute->load([
            'quest.client:id,name,email',
            'quest.freelancer:id,name,email',
            'openedBy:id,name,email',
            'assignedStaff:id,name,email',
            'messages.user:id,name,email',
            'events.actor:id,name,email',
        ]);

        $maxMinor = (int) config('operations.dispute_staff_ruling_max_minor', 5_000_000_00);
        $canRule = (int) ($dispute->disputed_amount_minor ?? 0) <= $maxMinor;

        return [
            'dispute' => $this->row($dispute, true),
            'parties' => [
                'client' => $dispute->quest?->client?->only(['id', 'name', 'email']),
                'freelancer' => $dispute->quest?->freelancer?->only(['id', 'name', 'email']),
            ],
            'messages' => $dispute->messages->map(fn (DisputeMessage $message) => [
                'id' => $message->id,
                'kind' => $message->kind?->value ?? (string) $message->kind,
                'body' => $message->body,
                'author' => $message->user?->name ?? 'System',
                'created_at' => $message->created_at?->toIso8601String(),
            ]),
            'events' => $dispute->events->map(fn (DisputeEvent $event) => [
                'id' => $event->id,
                'action' => $event->action,
                'properties' => $event->properties,
                'actor' => $event->actor?->name ?? 'System',
                'created_at' => $event->created_at?->toIso8601String(),
            ]),
            'internal_notes' => $dispute->events
                ->where('action', 'staff_internal_note')
                ->values()
                ->map(fn (DisputeEvent $event) => [
                    'body' => data_get($event->properties, 'body'),
                    'actor' => $event->actor?->name,
                    'created_at' => $event->created_at?->toIso8601String(),
                ]),
            'permissions' => [
                'can_issue_ruling' => $canRule,
                'ruling_max_minor' => $maxMinor,
            ],
        ];
    }

    public function claim(QuestDispute $dispute, User $staff, Request $request): QuestDispute
    {
        if ($dispute->assigned_staff_id && (int) $dispute->assigned_staff_id !== (int) $staff->id) {
            throw ValidationException::withMessages(['claim' => 'This dispute is already assigned to another staff member.']);
        }

        $dispute->forceFill([
            'assigned_staff_id' => $staff->id,
            'staff_claimed_at' => now(),
        ])->save();

        $this->recordEvent($dispute, $staff, 'staff_claimed', ['staff_id' => $staff->id]);
        $this->logger->log($staff, 'operations.dispute.claimed', QuestDispute::class, $dispute->id, [], $request);

        return $dispute->fresh();
    }

    public function internalNote(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->recordEvent($dispute, $staff, 'staff_internal_note', ['body' => $data['body']]);
        $this->logger->log($staff, 'operations.dispute.internal_note', QuestDispute::class, $dispute->id, [], $request);
    }

    public function postNotice(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        DisputeMessage::query()->create([
            'quest_dispute_id' => $dispute->id,
            'user_id' => $staff->id,
            'kind' => DisputeMessageKind::System,
            'body' => $data['body'],
            'structured_key' => 'staff_notice',
            'structured_payload' => ['audience' => $data['audience'] ?? 'both'],
        ]);

        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $recipients = $this->noticeRecipients($dispute, $data['audience'] ?? 'both');
        foreach ($recipients as $recipient) {
            $recipient->notify(new AdminUserMessageNotification($data['subject'] ?? 'Dispute notice', $data['body']));
            if ($recipient->email) {
                Mail::raw($data['body'], fn ($mail) => $mail->to($recipient->email)->subject($data['subject'] ?? 'Dispute update'));
            }
        }

        $this->recordEvent($dispute, $staff, 'staff_notice', ['audience' => $data['audience'] ?? 'both']);
        $this->logger->log($staff, 'operations.dispute.notice', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function contactParty(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $dispute->loadMissing('quest.client', 'quest.freelancer');
        $recipient = match ($data['party']) {
            'client' => $dispute->quest?->client,
            'freelancer' => $dispute->quest?->freelancer,
            default => null,
        };

        if ($recipient === null) {
            throw ValidationException::withMessages(['party' => 'Party not found on this dispute.']);
        }

        $recipient->notify(new AdminUserMessageNotification($data['subject'], $data['body']));
        if (($data['channel'] ?? 'both') !== 'in_app' && $recipient->email) {
            Mail::raw($data['body'], fn ($mail) => $mail->to($recipient->email)->subject($data['subject']));
        }

        $this->recordEvent($dispute, $staff, 'staff_contact', ['party' => $data['party'], 'channel' => $data['channel'] ?? 'both']);
        $this->logger->log($staff, 'operations.dispute.contact', QuestDispute::class, $dispute->id, $data, $request);
    }

    public function requestEvidence(QuestDispute $dispute, User $staff, array $data, Request $request): void
    {
        $this->postNotice($dispute, $staff, [
            'subject' => 'Additional evidence requested',
            'body' => $data['body'],
            'audience' => $data['audience'] ?? 'both',
        ], $request);

        $this->recordEvent($dispute, $staff, 'staff_evidence_request', ['body' => $data['body']]);
    }

    public function setTier(QuestDispute $dispute, User $staff, array $data, Request $request): QuestDispute
    {
        $dispute->forceFill(['tier' => (int) $data['tier']])->save();
        $this->recordEvent($dispute, $staff, 'staff_tier_change', ['tier' => (int) $data['tier'], 'note' => $data['note'] ?? null]);
        $this->logger->log($staff, 'operations.dispute.tier', QuestDispute::class, $dispute->id, $data, $request);

        return $dispute->fresh();
    }

    public function issueRuling(QuestDispute $dispute, User $staff, array $data, Request $request): QuestDispute
    {
        $maxMinor = (int) config('operations.dispute_staff_ruling_max_minor', 5_000_000_00);
        if ((int) ($dispute->disputed_amount_minor ?? 0) > $maxMinor) {
            throw ValidationException::withMessages(['ruling' => 'Dispute value exceeds staff ruling threshold. Escalate to Super Admin.']);
        }

        return DB::transaction(function () use ($dispute, $staff, $data, $request): QuestDispute {
            $dispute->forceFill([
                'status' => QuestDisputeStatus::Resolved,
                'resolved_at' => now(),
                'resolution_outcome' => $data['outcome'],
                'final_client_share_percent' => $data['client_share_percent'],
                'ruling_favoured_user_id' => $data['favoured_user_id'] ?? null,
            ])->save();

            $this->postNotice($dispute, $staff, [
                'subject' => 'Dispute ruling issued',
                'body' => $data['summary'],
                'audience' => 'both',
            ], $request);

            $this->recordEvent($dispute, $staff, 'staff_ruling', $data);
            $this->logger->log($staff, 'operations.dispute.ruling', QuestDispute::class, $dispute->id, $data, $request);

            return $dispute->fresh();
        });
    }

    private function row(QuestDispute $dispute, bool $expanded = false): array
    {
        $base = [
            'id' => $dispute->id,
            'uuid' => $dispute->uuid,
            'status' => $dispute->status?->value ?? (string) $dispute->status,
            'phase' => $dispute->phase?->value ?? (string) $dispute->phase,
            'tier' => $dispute->tier,
            'quest' => $dispute->quest?->title,
            'quest_reference' => $dispute->quest?->reference_code,
            'quest_id' => $dispute->quest_id,
            'opened_by' => $dispute->openedBy?->name,
            'assigned_staff' => $dispute->assignedStaff?->name,
            'disputed_amount_minor' => $dispute->disputed_amount_minor,
            'created_at' => $dispute->created_at?->toIso8601String(),
        ];

        if (! $expanded) {
            return $base;
        }

        return array_merge($base, [
            'reason' => $dispute->reason,
            'opening_summary' => $dispute->opening_summary,
            'structured_intake' => $dispute->structured_intake,
            'resolution_outcome' => $dispute->resolution_outcome,
            'staff_claimed_at' => $dispute->staff_claimed_at?->toIso8601String(),
        ]);
    }

    private function recordEvent(QuestDispute $dispute, User $staff, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $staff->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    /**
     * @return list<User>
     */
    private function noticeRecipients(QuestDispute $dispute, string $audience): array
    {
        $client = $dispute->quest?->client;
        $freelancer = $dispute->quest?->freelancer;

        return match ($audience) {
            'client' => array_filter([$client]),
            'freelancer' => array_filter([$freelancer]),
            default => array_filter([$client, $freelancer]),
        };
    }
}
