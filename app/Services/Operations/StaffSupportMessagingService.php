<?php

namespace App\Services\Operations;

use App\Mail\StaffBulkMessageMail;
use App\Mail\SupportTicketStatusMail;
use App\Models\AdminNotification;
use App\Models\Quest;
use App\Models\QuestConversationThread;
use App\Models\StaffBulkMessageRequest;
use App\Models\SupportChatAssignment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class StaffSupportMessagingService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function ensureAssignments(): void
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            return;
        }

        $admins = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->orderBy('id')
            ->pluck('id')
            ->values();

        if ($admins->isEmpty()) {
            return;
        }

        $lastAssigned = SupportChatAssignment::query()->latest('id')->value('assigned_admin_id');
        $lastIndex = $admins->search($lastAssigned);
        $cursor = $lastIndex === false ? 0 : $lastIndex + 1;

        QuestConversationThread::query()
            ->whereNotIn('id', SupportChatAssignment::query()->select('quest_conversation_thread_id')->whereNotNull('quest_conversation_thread_id'))
            ->oldest('created_at')
            ->limit(50)
            ->get(['id'])
            ->each(function (QuestConversationThread $thread) use ($admins, &$cursor): void {
                $adminId = $admins[$cursor % $admins->count()];
                $cursor++;

                SupportChatAssignment::query()->create([
                    'quest_conversation_thread_id' => $thread->id,
                    'assigned_admin_id' => $adminId,
                    'status' => 'open',
                    'assigned_at' => now(),
                ]);
            });
    }

    public function assignedChats(User $admin): Collection
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            return collect();
        }

        return SupportChatAssignment::query()
            ->with(['thread.quest:id,title,reference_code', 'thread.client:id,name,email', 'thread.freelancer:id,name,email', 'admin:id,name,email'])
            ->where('assigned_admin_id', $admin->id)
            ->latest('assigned_at')
            ->limit(12)
            ->get()
            ->map(fn (SupportChatAssignment $assignment) => $this->chatPayload($assignment));
    }

    public function allChats(): Collection
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            return collect();
        }

        return SupportChatAssignment::query()
            ->with(['thread.quest:id,title,reference_code', 'thread.client:id,name,email', 'thread.freelancer:id,name,email', 'admin:id,name,email'])
            ->latest('assigned_at')
            ->limit(30)
            ->get()
            ->map(fn (SupportChatAssignment $assignment) => $this->chatPayload($assignment));
    }

    public function ticketsForStaff(User $admin): LengthAwarePaginator
    {
        if (! Schema::hasTable('support_tickets')) {
            return $this->emptyTicketPaginator();
        }

        return SupportTicket::query()
            ->with(['customer:id,name,email', 'assignedAdmin:id,name,email', 'openedByAdmin:id,name,email', 'thread.quest:id,title,reference_code', 'messages.sender:id,name,email'])
            ->where(function ($query) use ($admin): void {
                $query->where('assigned_admin_id', $admin->id)
                    ->orWhere('opened_by_admin_id', $admin->id);
            })
            ->latest()
            ->paginate(15)
            ->through(fn (SupportTicket $ticket) => $this->ticketPayload($ticket));
    }

    public function ticketsForAdmin(): LengthAwarePaginator
    {
        if (! Schema::hasTable('support_tickets')) {
            return $this->emptyTicketPaginator(20);
        }

        return SupportTicket::query()
            ->with(['customer:id,name,email', 'assignedAdmin:id,name,email', 'openedByAdmin:id,name,email', 'thread.quest:id,title,reference_code', 'messages.sender:id,name,email'])
            ->latest()
            ->paginate(20)
            ->through(fn (SupportTicket $ticket) => $this->ticketPayload($ticket));
    }

    public function createTicket(User $admin, array $data): SupportTicket
    {
        if (! Schema::hasTable('support_tickets')) {
            throw new \RuntimeException('Support ticketing tables are not installed. Run database migrations.');
        }

        return DB::transaction(function () use ($admin, $data): SupportTicket {
            $ticket = SupportTicket::query()->create([
                ...$data,
                'opened_by_admin_id' => $admin->id,
                'assigned_admin_id' => $admin->id,
                'status' => 'open',
                'opened_at' => now(),
            ]);

            $ticket->messages()->create([
                'sender_user_id' => $admin->id,
                'sender_type' => 'admin',
                'visibility' => 'public',
                'body' => $data['description'],
                'metadata' => ['event' => 'ticket_opened'],
            ]);

            $this->logger->log($admin, 'support_ticket.opened', SupportTicket::class, $ticket->id, [
                'subject' => $ticket->subject,
                'customer_id' => $ticket->user_id,
            ]);

            $ticket->load('customer');
            if ($ticket->customer?->email) {
                Mail::to($ticket->customer->email)->send(new SupportTicketStatusMail($ticket, 'opened'));
            }

            return $ticket;
        });
    }

    public function updateTicketStatus(User $admin, SupportTicket $ticket, string $status, string $reason): SupportTicket
    {
        return DB::transaction(function () use ($admin, $ticket, $status, $reason): SupportTicket {
            $ticket->forceFill([
                'status' => $status,
                'resolution_summary' => in_array($status, ['resolved', 'closed'], true) ? $reason : $ticket->resolution_summary,
                'closed_at' => $status === 'closed' ? now() : null,
            ])->save();

            $ticket->messages()->create([
                'sender_user_id' => $admin->id,
                'sender_type' => in_array((string) $admin->role?->slug, ['admin', 'super_admin'], true) ? 'admin' : 'system',
                'visibility' => 'public',
                'body' => $reason,
                'metadata' => ['event' => 'status_changed', 'status' => $status],
            ]);

            $this->logger->log($admin, 'support_ticket.status_changed', SupportTicket::class, $ticket->id, [
                'status' => $status,
            ]);

            if ($status === 'closed') {
                $ticket->load('customer');
                if ($ticket->customer?->email) {
                    Mail::to($ticket->customer->email)->send(new SupportTicketStatusMail($ticket, 'closed'));
                }
            }

            return $ticket;
        });
    }

    public function createBulkMessageRequest(User $admin, array $data): StaffBulkMessageRequest
    {
        if (! Schema::hasTable('staff_bulk_message_requests')) {
            throw new \RuntimeException('Bulk messaging tables are not installed. Run database migrations.');
        }

        return DB::transaction(function () use ($admin, $data): StaffBulkMessageRequest {
            $request = StaffBulkMessageRequest::query()->create([
                ...$data,
                'created_by_admin_id' => $admin->id,
                'status' => 'pending_authorisation',
                'recipients_count' => $this->recipientQuery($data['audience'])->count(),
            ]);

            $superAdmins = User::query()
                ->whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))
                ->get(['id']);

            foreach ($superAdmins as $superAdmin) {
                AdminNotification::query()->create([
                    'admin_user_id' => $superAdmin->id,
                    'category' => 'messaging',
                    'priority' => 'high',
                    'title' => 'Bulk message needs authorisation',
                    'body' => "{$admin->name} requested approval for: {$request->subject}",
                    'action_label' => 'Review request',
                    'action_url' => route('admin.support-tickets.index'),
                    'data' => ['bulk_message_request_id' => $request->id],
                ]);
            }

            $this->logger->log($admin, 'bulk_message.requested', StaffBulkMessageRequest::class, $request->id, [
                'audience' => $request->audience,
                'channels' => $request->channels,
            ]);

            return $request;
        });
    }

    public function approveBulkMessage(User $superAdmin, StaffBulkMessageRequest $request, ?string $note = null): StaffBulkMessageRequest
    {
        return DB::transaction(function () use ($superAdmin, $request, $note): StaffBulkMessageRequest {
            if ($request->status !== 'pending_authorisation') {
                return $request;
            }

            $request->forceFill([
                'approved_by_admin_id' => $superAdmin->id,
                'status' => 'approved',
                'approval_note' => $note,
                'approved_at' => now(),
            ])->save();

            if (in_array('mail', $request->channels ?? [], true) || in_array('in_app', $request->channels ?? [], true)) {
                $this->recipientQuery($request->audience)->limit(500)->get()->each(function (User $recipient) use ($request): void {
                    if ($recipient->email) {
                        if (in_array('mail', $request->channels ?? [], true)) {
                            Mail::to($recipient->email)->send(new StaffBulkMessageMail($request, $recipient));
                        }
                    }

                    if (in_array('in_app', $request->channels ?? [], true)) {
                        $recipient->notify(new AdminUserMessageNotification($request->subject, $request->body));
                    }
                });
            }

            $request->forceFill([
                'status' => 'dispatched',
                'dispatched_at' => now(),
            ])->save();

            $this->logger->log($superAdmin, 'bulk_message.approved_dispatched', StaffBulkMessageRequest::class, $request->id, [
                'audience' => $request->audience,
                'channels' => $request->channels,
            ]);

            return $request;
        });
    }

    public function pendingBulkRequestsForStaff(User $admin): Collection
    {
        if (! Schema::hasTable('staff_bulk_message_requests')) {
            return collect();
        }

        return StaffBulkMessageRequest::query()
            ->with(['createdByAdmin:id,name,email', 'approvedByAdmin:id,name,email'])
            ->where('created_by_admin_id', $admin->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StaffBulkMessageRequest $request) => $this->bulkRequestPayload($request));
    }

    public function pendingBulkRequests(): Collection
    {
        if (! Schema::hasTable('staff_bulk_message_requests')) {
            return collect();
        }

        return StaffBulkMessageRequest::query()
            ->with(['createdByAdmin:id,name,email', 'approvedByAdmin:id,name,email'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StaffBulkMessageRequest $request) => $this->bulkRequestPayload($request));
    }

    public function supportTablesReady(): bool
    {
        return Schema::hasTable('support_tickets')
            && Schema::hasTable('support_ticket_messages')
            && Schema::hasTable('support_chat_assignments')
            && Schema::hasTable('staff_bulk_message_requests');
    }

    public function unassignedChats(): Collection
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            return collect();
        }

        return SupportChatAssignment::query()
            ->with(['thread.quest:id,title,reference_code', 'thread.client:id,name,email', 'thread.freelancer:id,name,email'])
            ->where(fn ($query) => $query->whereNull('assigned_admin_id')->orWhere('status', 'unassigned'))
            ->latest('created_at')
            ->limit(30)
            ->get()
            ->map(fn (SupportChatAssignment $assignment) => $this->chatPayload($assignment));
    }

    public function claimChat(SupportChatAssignment $assignment, User $staff): SupportChatAssignment
    {
        $assignment->forceFill([
            'assigned_admin_id' => $staff->id,
            'status' => 'open',
            'assigned_at' => now(),
        ])->save();

        $this->logger->log($staff, 'support_chat.claimed', SupportChatAssignment::class, $assignment->id, []);

        return $assignment->fresh(['thread.quest', 'thread.client', 'thread.freelancer', 'admin']);
    }

    public function chatThread(SupportChatAssignment $assignment): array
    {
        $assignment->load(['thread.messages.user:id,name,email', 'thread.quest', 'thread.client', 'thread.freelancer', 'admin']);

        return [
            'assignment' => $this->chatPayload($assignment),
            'messages' => $assignment->thread?->messages->map(fn ($message) => [
                'id' => $message->id,
                'body' => $message->body,
                'sender' => $message->user?->name,
                'sender_id' => $message->user_id,
                'created_at' => $message->created_at?->toIso8601String(),
            ]) ?? collect(),
        ];
    }

    public function replyToChat(SupportChatAssignment $assignment, User $staff, string $body): void
    {
        $assignment->loadMissing('thread');
        if ($assignment->thread === null) {
            throw new \RuntimeException('Conversation thread missing.');
        }

        $assignment->thread->messages()->create([
            'user_id' => $staff->id,
            'body' => $body,
        ]);

        $assignment->thread->forceFill(['last_message_at' => now()])->save();
        $this->logger->log($staff, 'support_chat.reply', SupportChatAssignment::class, $assignment->id, []);
    }

    public function userContext(User $user): array
    {
        $user->loadMissing('role');

        $activeQuests = Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->latest()
            ->limit(8)
            ->get(['id', 'title', 'reference_code', 'status', 'escrow_status']);

        $verifications = $user->userVerifications()
            ->latest()
            ->limit(5)
            ->get(['id', 'category', 'verification_type', 'status', 'submitted_at']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'verification_level' => $user->current_verification_level ?? $user->verification_tier ?? 0,
            ],
            'active_quests' => $activeQuests->map(fn (Quest $quest) => [
                'id' => $quest->id,
                'title' => $quest->title,
                'reference_code' => $quest->reference_code,
                'status' => $quest->status?->value ?? (string) $quest->status,
                'escrow_status' => $quest->escrow_status,
            ]),
            'verifications' => $verifications->map(fn ($v) => [
                'id' => $v->id,
                'type' => $v->verification_type ?: $v->category?->value,
                'status' => $v->status?->value ?? (string) $v->status,
                'submitted_at' => $v->submitted_at?->toIso8601String(),
            ]),
            'recent_activity' => [],
        ];
    }

    public function sendPanelEmail(User $staff, User $recipient, array $data): void
    {
        $recipient->notify(new AdminUserMessageNotification($data['subject'], $data['body']));

        if (($data['channel'] ?? 'both') !== 'in_app' && $recipient->email) {
            Mail::raw($data['body'], fn ($mail) => $mail->to($recipient->email)->subject($data['subject']));
        }

        $this->logger->log($staff, 'operations.panel_email', User::class, $recipient->id, [
            'subject' => $data['subject'],
            'context' => $data['context'] ?? null,
        ]);
    }

    private function bulkRequestPayload(StaffBulkMessageRequest $request): array
    {
        return [
            'id' => $request->id,
            'subject' => $request->subject,
            'body' => $request->body,
            'audience' => $request->audience,
            'channels' => $request->channels ?? [],
            'status' => $request->status,
            'recipients_count' => $request->recipients_count,
            'created_at' => $request->created_at?->toIso8601String(),
            'created_by' => $request->createdByAdmin?->name,
            'approved_by' => $request->approvedByAdmin?->name,
        ];
    }

    private function emptyTicketPaginator(int $perPage = 15): LengthAwarePaginator
    {
        return new Paginator([], 0, $perPage, 1, [
            'path' => request()->url(),
            'query' => request()->query(),
        ]);
    }

    private function recipientQuery(string $audience)
    {
        return User::query()
            ->when($audience === 'clients', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('slug', 'client')))
            ->when($audience === 'freelancers', fn ($query) => $query->whereHas('role', fn ($role) => $role->where('slug', 'freelancer')))
            ->when($audience === 'verified_users', fn ($query) => $query->whereNotNull('email_verified_at'));
    }

    private function chatPayload(SupportChatAssignment $assignment): array
    {
        $thread = $assignment->thread;

        return [
            'id' => $assignment->id,
            'thread_id' => $thread?->id,
            'quest' => $thread?->quest?->title,
            'reference_code' => $thread?->quest?->reference_code,
            'client' => $thread?->client ? ['id' => $thread->client->id, 'name' => $thread->client->name, 'email' => $thread->client->email] : null,
            'freelancer' => $thread?->freelancer ? ['id' => $thread->freelancer->id, 'name' => $thread->freelancer->name, 'email' => $thread->freelancer->email] : null,
            'assigned_admin' => $assignment->admin?->name,
            'status' => $assignment->status,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'last_message_at' => $thread?->last_message_at?->toIso8601String(),
            'messages_count' => $thread?->messages_count,
        ];
    }

    public function ticketPayload(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'description' => $ticket->description,
            'resolution_summary' => $ticket->resolution_summary,
            'opened_at' => $ticket->opened_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'created_at' => $ticket->created_at?->toIso8601String(),
            'age_hours' => $ticket->created_at ? (int) $ticket->created_at->diffInHours(now()) : 0,
            'customer' => $ticket->customer ? ['id' => $ticket->customer->id, 'name' => $ticket->customer->name, 'email' => $ticket->customer->email] : null,
            'assigned_admin' => $ticket->assignedAdmin?->name,
            'opened_by' => $ticket->openedByAdmin?->name,
            'quest' => $ticket->thread?->quest?->title,
            'messages' => $ticket->messages->map(fn ($message) => [
                'id' => $message->id,
                'sender' => $message->sender?->name ?? $message->sender_type,
                'sender_type' => $message->sender_type,
                'visibility' => $message->visibility,
                'body' => $message->body,
                'metadata' => $message->metadata,
                'created_at' => $message->created_at?->toIso8601String(),
            ])->values(),
        ];
    }
}
