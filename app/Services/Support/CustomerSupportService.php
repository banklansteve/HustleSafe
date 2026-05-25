<?php

namespace App\Services\Support;

use App\Events\CustomerSupportChatAssigned;
use App\Events\CustomerSupportMessageSent;
use App\Events\CustomerSupportQueueChanged;
use App\Events\CustomerSupportSessionUpdated;
use App\Events\CustomerSupportTyping;
use App\Models\KycReviewCase;
use App\Mail\SupportChatRatingMail;
use App\Models\AdminFinancialLedgerEntry;
use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\SupportTicket;
use App\Models\SupportTicketHandoff;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Services\Operations\StaffNotificationCentreService;
use App\Services\UserNotificationInboxService;
use App\Support\MessagingViewPresence;
use App\Support\ChatAttachmentHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

class CustomerSupportService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly StaffNotificationCentreService $notificationCentre,
        private readonly UserNotificationInboxService $userNotifications,
    ) {}

    public function tablesReady(): bool
    {
        return Schema::hasTable('support_tickets') && Schema::hasTable('support_ticket_messages');
    }

    /**
     * @return list<string, string>
     */
    public function categories(): array
    {
        return collect(config('customer_support.categories', []))
            ->mapWithKeys(fn (array $meta, string $key) => [$key => $meta['label'] ?? $key])
            ->all();
    }

    public function startChat(User $customer, array $data): SupportTicket
    {
        $category = (string) $data['category'];
        $meta = config("customer_support.categories.{$category}");
        if ($meta === null) {
            throw ValidationException::withMessages(['category' => 'Invalid category.']);
        }

        return DB::transaction(function () use ($customer, $data, $category, $meta): SupportTicket {
            $now = now();
            $ticket = SupportTicket::query()->create([
                'user_id' => $customer->id,
                'customer_username' => $customer->username ?? $customer->email,
                'customer_full_name' => $customer->name,
                'subject' => $data['subject'],
                'category' => $category,
                'priority' => $meta['priority'] ?? 'medium',
                'status' => 'open',
                'chat_status' => 'queued',
                'description' => $data['description'] ?? null,
                'opened_at' => $now,
                'queued_at' => $now,
                'last_activity_at' => $now,
                'last_user_activity_at' => $now,
            ]);

            if (! empty($data['initial_message'])) {
                $message = $this->createMessage($ticket, $customer, 'customer', (string) $data['initial_message'], [], 'public');
                $ticket->forceFill(['user_last_read_message_id' => $message->id])->save();
                $this->broadcastMessage($ticket, $this->messagePayload($message->loadMissing('sender'), $customer));
            }

            $this->assignNextAvailableAdmin($ticket);

            return $ticket->fresh(['customer', 'assignedAdmin']);
        });
    }

    public function assignNextAvailableAdmin(SupportTicket $ticket): void
    {
        if ($ticket->assigned_admin_id !== null || $ticket->chat_status === 'closed') {
            return;
        }

        $onlineAdmins = $this->onlineAdminIds();
        if ($onlineAdmins->isEmpty()) {
            $this->notificationCentre->notifyCustomerSupportQueued($ticket);

            return;
        }

        $loads = SupportTicket::query()
            ->whereIn('assigned_admin_id', $onlineAdmins)
            ->whereIn('chat_status', ['queued', 'active'])
            ->selectRaw('assigned_admin_id, COUNT(*) as load_count')
            ->groupBy('assigned_admin_id')
            ->pluck('load_count', 'assigned_admin_id');

        $adminId = $onlineAdmins
            ->sortBy(fn (int $id) => (int) ($loads[$id] ?? 0))
            ->first();

        $ticket->forceFill([
            'assigned_admin_id' => $adminId,
            'chat_status' => 'active',
            'status' => 'open',
        ])->save();

        $ticket->load('customer');
        if ($ticket->customer) {
            $this->broadcastSessionUpdate($ticket, $ticket->customer);
        }

        $ticket->load(['customer', 'assignedAdmin']);
        $admin = User::query()->find($adminId);
        if ($admin) {
            $this->notifyAdminAssigned($admin, $ticket);
        }
    }

    public function notifyAdminAssigned(User $admin, SupportTicket $ticket): void
    {
        if ($admin->role?->slug !== 'admin') {
            return;
        }

        $payload = $this->ticketListPayload($ticket, $admin);
        $this->notificationCentre->notifyCustomerSupportAssigned($admin, $ticket);
        broadcast(new CustomerSupportChatAssigned($admin->id, $payload));
        $this->broadcastQueueChanged($ticket, 'assigned');
    }

    /**
     * @return Collection<int, int>
     */
    public function onlineAdminIds(): Collection
    {
        $window = (int) config('customer_support.online_window_minutes', 5);

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->where('last_active_at', '>=', now()->subMinutes($window))
            ->orderBy('id')
            ->pluck('id');
    }

    public function reassignChat(User $actor, SupportTicket $ticket, int $adminId): SupportTicket
    {
        abort_unless($actor->role?->slug === 'super_admin', 403);

        $admin = User::query()
            ->whereKey($adminId)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->firstOrFail();

        if ($admin->role?->slug === 'super_admin' && (int) $admin->id !== (int) $actor->id) {
            abort(422, __('Live support chats cannot be assigned to another super admin.'));
        }

        if ($admin->role?->slug !== 'admin' && (int) $admin->id !== (int) $actor->id) {
            abort(422, __('Assign chats to staff admins only.'));
        }

        $fromAdminId = $ticket->assigned_admin_id;
        $handoffMessageId = (int) $ticket->messages()->max('id');

        if ($fromAdminId && (int) $fromAdminId !== (int) $admin->id) {
            SupportTicketHandoff::query()->create([
                'support_ticket_id' => $ticket->id,
                'from_admin_id' => $fromAdminId,
                'to_admin_id' => $admin->id,
                'reassigned_by_id' => $actor->id,
                'handoff_message_id' => $handoffMessageId > 0 ? $handoffMessageId : null,
            ]);
        }

        $ticket->forceFill([
            'assigned_admin_id' => $admin->id,
            'chat_status' => $ticket->chat_status === 'closed' ? 'closed' : 'active',
        ])->save();

        $this->logger->log($actor, 'customer_support.reassigned', SupportTicket::class, $ticket->id, [
            'admin_id' => $admin->id,
        ]);

        $ticket = $ticket->fresh(['customer', 'assignedAdmin']);
        $this->notifyAdminAssigned($admin, $ticket);
        if ($ticket->customer) {
            $this->broadcastSessionUpdate($ticket, $ticket->customer);
        }

        return $ticket;
    }

    public function endConversation(User $admin, SupportTicket $ticket, ?string $note = null): SupportTicket
    {
        return $this->closeTicket($ticket, $admin, $note ?? 'Conversation ended by support.');
    }

    public function closeTicket(SupportTicket $ticket, ?User $actor = null, ?string $note = null): SupportTicket
    {
        if ($ticket->chat_status === 'closed') {
            return $ticket;
        }

        $openedAt = $ticket->opened_at ?? $ticket->created_at ?? now();

        return DB::transaction(function () use ($ticket, $actor, $note, $openedAt): SupportTicket {
            $ticket->forceFill([
                'chat_status' => 'closed',
                'status' => 'closed',
                'closed_at' => now(),
                'resolution_seconds' => (int) $openedAt->diffInSeconds(now()),
                'resolution_summary' => $note ?? $ticket->resolution_summary,
            ])->save();

            if ($note && $actor && ! ($ticket->isLiveChat() && $ticket->user_id)) {
                $this->createMessage($ticket, $actor, 'admin', $note, [], 'public');
            }

            if ($actor) {
                $this->logger->log($actor, 'customer_support.closed', SupportTicket::class, $ticket->id, []);
            }

            $ticket = $ticket->fresh(['customer', 'assignedAdmin']);

            if ($ticket->isLiveChat() && $ticket->user_id) {
                $this->postSessionClosedMessage($ticket, $actor);
                $this->sendRatingEmailOnClose($ticket);
            }

            if ($ticket->customer) {
                $this->broadcastSessionUpdate($ticket, $ticket->customer);
            }

            $this->broadcastQueueChanged($ticket, 'closed');
            $this->notificationCentre->markCustomerSupportNotificationsReadForTicket((int) $ticket->id);

            return $ticket;
        });
    }

    public function closeInactiveChats(): int
    {
        $minutes = (int) config('customer_support.inactivity_close_minutes', 30);
        $cutoff = now()->subMinutes($minutes);
        $closed = 0;

        SupportTicket::query()
            ->whereIn('chat_status', ['queued', 'active'])
            ->where(function ($q) use ($cutoff): void {
                $q->where('last_activity_at', '<', $cutoff)
                    ->orWhereNull('last_activity_at');
            })
            ->each(function (SupportTicket $ticket) use (&$closed): void {
                $this->closeTicket($ticket, null, 'Closed automatically after inactivity.');
                $closed++;
            });

        return $closed;
    }

    public function dispatchDueRatingEmails(): int
    {
        $delay = (int) config('customer_support.rating_email_delay_minutes', 30);
        $sent = 0;

        SupportTicket::query()
            ->where('chat_status', 'closed')
            ->whereNull('rating_email_sent_at')
            ->whereNull('rated_at')
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', now()->subMinutes($delay))
            ->with('customer')
            ->limit(100)
            ->get()
            ->each(function (SupportTicket $ticket) use (&$sent): void {
                if (! $ticket->customer?->email) {
                    $ticket->forceFill(['rating_email_sent_at' => now()])->save();

                    return;
                }

                Mail::to($ticket->customer->email)->send(new SupportChatRatingMail($ticket));
                $ticket->forceFill(['rating_email_sent_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    /**
     * @param  array{score: int, reaction?: string|null, comment?: string|null, answers?: array<string, string>|null}  $data
     */
    public function recordFeedback(SupportTicket $ticket, array $data): SupportTicket
    {
        if ($ticket->rated_at !== null) {
            throw ValidationException::withMessages([
                'feedback' => __('You have already submitted feedback for this support session.'),
            ]);
        }

        $score = (int) ($data['score'] ?? 0);
        if ($score < 1 || $score > 10) {
            throw ValidationException::withMessages(['score' => __('Please choose a score between 1 and 10.')]);
        }

        $reaction = $data['reaction'] ?? null;
        if ($reaction !== null && ! $this->isValidReactionKey($reaction)) {
            throw ValidationException::withMessages(['reaction' => __('Invalid reaction.')]);
        }

        $ticket->forceFill([
            'rating_score' => $score,
            'rating_stars' => (int) max(1, min(5, (int) round($score / 2))),
            'rating_reaction' => $reaction,
            'rating_comment' => $data['comment'] ?? null,
            'feedback_answers' => $data['answers'] ?? null,
            'rated_at' => now(),
        ])->save();

        return $ticket->fresh();
    }

    /** @deprecated Use recordFeedback() — kept for internal callers migrating from stars. */
    public function recordRating(SupportTicket $ticket, int $stars, ?string $comment = null): SupportTicket
    {
        return $this->recordFeedback($ticket, [
            'score' => max(1, min(10, $stars * 2)),
            'comment' => $comment,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function adminSupportRatingStats(int $adminUserId): array
    {
        $base = SupportTicket::query()
            ->where('assigned_admin_id', $adminUserId)
            ->whereNotNull('rated_at');

        $count = (clone $base)->count();
        $avg = (clone $base)->whereNotNull('rating_score')->avg('rating_score');

        $recent = (clone $base)
            ->whereNotNull('rating_score')
            ->orderByDesc('rated_at')
            ->limit(8)
            ->get(['id', 'rating_score', 'rating_reaction', 'subject', 'rated_at']);

        return [
            'sessions_rated' => $count,
            'average_score' => $avg !== null ? round((float) $avg, 1) : null,
            'recent' => $recent->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'score' => $t->rating_score,
                'reaction' => $t->rating_reaction,
                'subject' => $t->subject,
                'rated_at' => $t->rated_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function closureReactions(): array
    {
        return config('customer_support.closure_reactions', []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function feedbackSurveySteps(): array
    {
        return config('customer_support.feedback_survey', []);
    }

    public function sendRatingEmailOnClose(SupportTicket $ticket): void
    {
        if (! config('customer_support.send_rating_email_on_close', true)) {
            return;
        }

        if (! $ticket->isLiveChat() || $ticket->rated_at !== null || $ticket->rating_email_sent_at !== null) {
            return;
        }

        $ticket->loadMissing('customer');

        if (! $ticket->customer?->email) {
            $ticket->forceFill(['rating_email_sent_at' => now()])->save();

            return;
        }

        try {
            Mail::to($ticket->customer->email)->send(new SupportChatRatingMail($ticket));
            $ticket->forceFill(['rating_email_sent_at' => now()])->save();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function postSessionClosedMessage(SupportTicket $ticket, ?User $actor): void
    {
        $feedbackUrl = $this->ratingUrl($ticket);

        $message = $ticket->messages()->create([
            'sender_user_id' => $actor?->id,
            'sender_type' => 'system',
            'visibility' => 'public',
            'body' => (string) config('customer_support.session_closed_customer_body'),
            'metadata' => [
                'kind' => 'session_closed',
                'feedback_url' => $feedbackUrl,
                'reactions' => $this->closureReactions(),
            ],
        ]);

        $message->loadMissing('sender');
        $this->broadcastMessage($ticket, $this->sessionClosedBroadcastPayload($message));
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionClosedBroadcastPayload(SupportTicketMessage $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        $customerBody = (string) config('customer_support.session_closed_customer_body');
        $adminBody = (string) config('customer_support.session_closed_admin_body');

        return [
            'id' => $message->id,
            'body' => $customerBody,
            'admin_body' => $adminBody,
            'visibility' => $message->visibility,
            'sender_type' => $message->sender_type,
            'kind' => 'session_closed',
            'feedback_url' => $metadata['feedback_url'] ?? null,
            'reactions' => $metadata['reactions'] ?? [],
            'sender' => null,
            'sender_label' => 'HustleSafe Support',
            'is_customer' => false,
            'is_admin_message' => false,
            'is_system' => true,
            'align' => 'center',
            'mine' => false,
            'attachments' => [],
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function isStaffViewer(User $viewer): bool
    {
        return in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
    }

    private function isValidReactionKey(string $key): bool
    {
        return collect($this->closureReactions())->contains(fn (array $r) => ($r['key'] ?? '') === $key);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<UploadedFile>|null  $files
     * @return array<string, mixed>
     */
    public function sendMessage(SupportTicket $ticket, User $sender, array $data, ?array $files = null): array
    {
        abort_if($ticket->isClosed(), 422, 'This conversation is closed.');
        abort_unless($this->canComposeOnTicket($ticket, $sender), 403, __('You cannot send messages on this conversation.'));

        $isAdmin = in_array($sender->role?->slug, ['admin', 'super_admin'], true);
        $visibility = ($data['visibility'] ?? 'public') === 'internal' && $isAdmin ? 'internal' : 'public';
        $senderType = $isAdmin ? 'admin' : 'customer';

        if ($visibility === 'internal' && ! $isAdmin) {
            abort(403);
        }

        $attachments = $this->storeAttachments($files);
        if (! empty($data['gif_url'])) {
            $attachments[] = ChatAttachmentHelper::remoteGif((string) $data['gif_url']);
        }

        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && $attachments === []) {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        $message = $this->createMessage($ticket, $sender, $senderType, $body, $attachments, $visibility);
        $message->loadMissing('sender');
        $now = now();

        $updates = ['last_activity_at' => $now];
        if ($isAdmin) {
            $updates['last_admin_activity_at'] = $now;
            $updates['admin_last_read_message_id'] = $message->id;
            if ($ticket->chat_status === 'queued') {
                $updates['chat_status'] = 'active';
                $updates['assigned_admin_id'] = $ticket->assigned_admin_id ?? $sender->id;
            }
        } else {
            $updates['last_user_activity_at'] = $now;
            $updates['user_last_read_message_id'] = $message->id;
        }

        $ticket->forceFill($updates)->save();

        $payload = $this->messageBroadcastPayload($message);
        $ticketId = (int) $ticket->id;
        $assignedAdminId = (int) $ticket->assigned_admin_id;
        $customerUserId = (int) $ticket->user_id;

        defer(function () use ($isAdmin, $sender, $ticket, $payload, $ticketId, $assignedAdminId, $customerUserId): void {
            $this->broadcastMessage($ticket, $payload);

            try {
                if ($isAdmin) {
                    $this->notificationCentre->markCustomerSupportNotificationsRead($sender, $ticketId);
                } else {
                    $this->userNotifications->markSupportChatForTicket($sender, $ticketId);
                }

                if (! $isAdmin && $assignedAdminId > 0) {
                    $admin = User::query()->find($assignedAdminId);
                    if ($admin && ! MessagingViewPresence::isViewing(
                        MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
                        $ticketId,
                        (int) $admin->id,
                    )) {
                        $this->notificationCentre->notifyCustomerSupportMessage($admin, $ticket, $sender);
                    }
                } elseif ($isAdmin && $customerUserId > 0) {
                    $customer = User::query()->find($customerUserId);
                    if ($customer && ! MessagingViewPresence::isViewing(
                        MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
                        $ticketId,
                        (int) $customer->id,
                    )) {
                        $this->notificationCentre->notifyCustomerSupportMessage($customer, $ticket, $sender);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function broadcastMessage(SupportTicket $ticket, array $payload): void
    {
        if (! $this->liveBroadcastEnabled()) {
            return;
        }

        try {
            broadcast(new CustomerSupportMessageSent($ticket->id, $payload));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function broadcastTyping(SupportTicket $ticket, User $user, bool $typing): void
    {
        if ($typing) {
            MessagingViewPresence::touch(
                MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
                (int) $ticket->id,
                (int) $user->id,
            );
        }

        $isAdmin = in_array($user->role?->slug, ['admin', 'super_admin'], true);
        $side = $isAdmin ? 'admin' : 'customer';
        $cacheKey = $this->typingCacheKey($ticket->id);
        $map = Cache::get($cacheKey, []);
        if (! is_array($map)) {
            $map = [];
        }

        if ($typing) {
            $map[(string) $user->id] = [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'name' => $user->name,
                'first_name' => $isAdmin ? $this->staffFirstName($user) : null,
                'typing' => true,
                'side' => $side,
            ];
        } else {
            unset($map[(string) $user->id]);
        }

        if ($map === []) {
            Cache::forget($cacheKey);
        } else {
            Cache::put($cacheKey, $map, now()->addSeconds(6));
        }

        if (! $this->liveBroadcastEnabled()) {
            return;
        }

        $ticketId = (int) $ticket->id;
        $userId = (int) $user->id;
        $userName = $user->name;
        $firstName = $isAdmin ? $this->staffFirstName($user) : null;

        defer(function () use ($ticketId, $userId, $userName, $typing, $side, $firstName): void {
            try {
                broadcast(new CustomerSupportTyping(
                    $ticketId,
                    $userId,
                    $userName,
                    $typing,
                    $side,
                    $firstName,
                ));
            } catch (\Throwable $e) {
                report($e);
            }
        });
    }

    /**
     * Active typing indicator for the other party (customer vs admin).
     *
     * @return array<string, mixed>|null
     */
    public function typingStateForTicket(SupportTicket $ticket, User $viewer): ?array
    {
        $map = Cache::get($this->typingCacheKey($ticket->id), []);
        if (! is_array($map) || $map === []) {
            return null;
        }

        $viewerIsAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
        $wantSide = $viewerIsAdmin ? 'customer' : 'admin';

        foreach ($map as $entry) {
            if (! is_array($entry) || ! ($entry['typing'] ?? false)) {
                continue;
            }
            if (($entry['side'] ?? '') === $wantSide) {
                return $entry;
            }
        }

        return null;
    }

    private function typingCacheKey(int $ticketId): string
    {
        return "support_chat_typing:{$ticketId}";
    }

    public function liveBroadcastEnabled(): bool
    {
        $driver = (string) config('broadcasting.default', 'null');

        if (in_array($driver, ['null', '', 'log'], true)) {
            return false;
        }

        return filled(config("broadcasting.connections.{$driver}.key"));
    }

    public function markRead(SupportTicket $ticket, User $reader, ?int $lastMessageId = null): void
    {
        $isAdmin = in_array($reader->role?->slug, ['admin', 'super_admin'], true);
        $field = $isAdmin ? 'admin_last_read_message_id' : 'user_last_read_message_id';

        if ($lastMessageId === null || $lastMessageId < 1) {
            return;
        }

        $current = (int) ($ticket->{$field} ?? 0);
        if ($lastMessageId <= $current) {
            MessagingViewPresence::touch(
                MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
                (int) $ticket->id,
                (int) $reader->id,
            );

            return;
        }

        SupportTicket::query()
            ->whereKey($ticket->id)
            ->where($field, '<', $lastMessageId)
            ->update([$field => $lastMessageId]);

        MessagingViewPresence::touch(
            MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
            (int) $ticket->id,
            (int) $reader->id,
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function messages(SupportTicket $ticket, User $viewer, ?int $beforeId = null, int $limit = 50): array
    {
        $isAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);

        $query = $ticket->messages()
            ->with('sender:id,name,username,avatar_url,role_id', 'sender.role:id,slug')
            ->orderByDesc('id');

        if (! $isAdmin) {
            $query->where('visibility', 'public');
        }

        $cutoff = $this->messageCutoffForViewer($ticket, $viewer);
        if ($cutoff !== null) {
            $query->where('id', '<=', $cutoff);
        }

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $rows = $rows->reverse()->values();

        return [
            'items' => $rows->map(fn (SupportTicketMessage $m) => $this->messagePayload($m, $viewer, $ticket))->all(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * Fast path for staff opening a chat: batched DB reads, lean message payloads.
     *
     * @return array{ticket: array<string, mixed>, messages: list<array<string, mixed>>, has_more: bool}
     */
    public function openTicketForAdmin(SupportTicket $ticket, User $admin): array
    {
        $ticket->load([
            'customer:id,name,email,username,current_verification_level,verification_tier',
            'assignedAdmin:id,name',
        ]);

        $handoff = $this->viewerHandoffContext($ticket, $admin);
        $result = $this->messagesForAdminInbox($ticket, $admin, null, 50, $handoff['cutoff']);
        $lastPreview = $result['items'] !== []
            ? (string) ($result['items'][array_key_last($result['items'])]['body'] ?? '')
            : '';

        return [
            'ticket' => $this->ticketDetailPayload(
                $ticket,
                $admin,
                0,
                $lastPreview !== '' ? $lastPreview : null,
                $handoff,
            ),
            'messages' => $result['items'],
            'has_more' => $result['has_more'],
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function messagesForAdminInbox(
        SupportTicket $ticket,
        User $admin,
        ?int $beforeId = null,
        int $limit = 50,
        ?int $messageCutoff = null,
    ): array {
        $query = $ticket->messages()
            ->select([
                'id',
                'support_ticket_id',
                'sender_user_id',
                'sender_type',
                'visibility',
                'body',
                'metadata',
                'created_at',
            ])
            ->with(['sender:id,name,username,avatar_url'])
            ->orderByDesc('id');

        $cutoff = $messageCutoff ?? $this->messageCutoffForViewer($ticket, $admin);
        if ($cutoff !== null) {
            $query->where('id', '<=', $cutoff);
        }

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        if ($hasMore) {
            $rows = $rows->take($limit);
        }

        $rows = $rows->reverse()->values();
        $otherReadId = (int) ($ticket->user_last_read_message_id ?? 0);
        $adminId = (int) $admin->id;

        return [
            'items' => $rows->map(fn (SupportTicketMessage $m) => $this->adminInboxMessagePayload($m, $admin, $adminId, $otherReadId))->all(),
            'has_more' => $hasMore,
        ];
    }

    /**
     * Full ticket row for an open chat (handoff / compose flags) without extra per-field queries.
     *
     * @param  array{cutoff: int|null, is_former: bool}|null  $handoffContext
     * @return array<string, mixed>
     */
    public function ticketDetailPayload(
        SupportTicket $ticket,
        User $viewer,
        ?int $unreadCount = null,
        ?string $lastMessagePreview = null,
        ?array $handoffContext = null,
    ): array {
        $handoffContext ??= $this->viewerHandoffContext($ticket, $viewer);

        if ($unreadCount === null) {
            $isAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
            $readField = $isAdmin ? 'admin_last_read_message_id' : 'user_last_read_message_id';
            $lastRead = (int) ($ticket->{$readField} ?? 0);
            $unreadCount = $ticket->messages()
                ->when(! $isAdmin, fn ($q) => $q->where('visibility', 'public'))
                ->when($isAdmin, fn ($q) => $q->where('sender_type', '!=', 'admin'))
                ->where('id', '>', $lastRead)
                ->count();
        }

        $payload = $this->ticketQueuePayload($ticket, $viewer, (int) $unreadCount, $lastMessagePreview);
        $payload['is_former_assignee'] = $handoffContext['is_former'];
        $payload['message_cutoff_id'] = $handoffContext['cutoff'];
        $payload['handoff_notice'] = $this->handoffNoticeFromContext($ticket, $viewer, $handoffContext);
        $payload['feedback_url'] = $ticket->isClosed() && $ticket->rated_at === null && $ticket->isLiveChat()
            ? $this->ratingUrl($ticket)
            : null;

        return $payload;
    }

    /**
     * @param  Collection<int, SupportTicket>  $tickets
     * @return list<array<string, mixed>>
     */
    private function mapTicketsForAdminDetailList(Collection $tickets, User $viewer): array
    {
        if ($tickets->isEmpty()) {
            return [];
        }

        $ids = $tickets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $unreadMap = $this->batchAdminUnreadCounts($ids);
        $previewMap = $this->batchLastMessageBodies($ids);
        $handoffMap = $this->batchViewerHandoffContexts($ids, $viewer);

        return $tickets
            ->map(function (SupportTicket $ticket) use ($viewer, $unreadMap, $previewMap, $handoffMap): array {
                $handoff = $handoffMap[$ticket->id] ?? ['cutoff' => null, 'is_former' => false];

                return $this->ticketDetailPayload(
                    $ticket,
                    $viewer,
                    (int) ($unreadMap[$ticket->id] ?? 0),
                    isset($previewMap[$ticket->id]) ? Str::limit((string) $previewMap[$ticket->id], 80) : null,
                    $handoff,
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return array{cutoff: int|null, is_former: bool}
     */
    private function viewerHandoffContext(SupportTicket $ticket, User $viewer): array
    {
        $empty = ['cutoff' => null, 'is_former' => false];

        if ($viewer->role?->slug === 'super_admin' || (int) $ticket->user_id === (int) $viewer->id) {
            return $empty;
        }

        if ((int) $ticket->assigned_admin_id === (int) $viewer->id) {
            return $empty;
        }

        if ($viewer->role?->slug !== 'admin') {
            return $empty;
        }

        $handoff = SupportTicketHandoff::query()
            ->where('support_ticket_id', $ticket->id)
            ->where('from_admin_id', $viewer->id)
            ->orderByDesc('id')
            ->first(['handoff_message_id']);

        if ($handoff === null) {
            return $empty;
        }

        return [
            'cutoff' => (int) $handoff->handoff_message_id,
            'is_former' => true,
        ];
    }

    /**
     * @param  list<int>  $ticketIds
     * @return array<int, array{cutoff: int|null, is_former: bool}>
     */
    private function batchViewerHandoffContexts(array $ticketIds, User $viewer): array
    {
        if ($ticketIds === [] || $viewer->role?->slug !== 'admin') {
            return [];
        }

        $rows = SupportTicketHandoff::query()
            ->whereIn('support_ticket_id', $ticketIds)
            ->where('from_admin_id', $viewer->id)
            ->orderByDesc('id')
            ->get(['support_ticket_id', 'handoff_message_id']);

        $map = [];
        foreach ($rows as $row) {
            if (isset($map[$row->support_ticket_id])) {
                continue;
            }

            $map[$row->support_ticket_id] = [
                'cutoff' => (int) $row->handoff_message_id,
                'is_former' => true,
            ];
        }

        return $map;
    }

    /**
     * @param  array{cutoff: int|null, is_former: bool}  $handoffContext
     */
    private function handoffNoticeFromContext(SupportTicket $ticket, User $viewer, array $handoffContext): ?string
    {
        if ($this->canComposeOnTicket($ticket, $viewer)) {
            return null;
        }

        if ($viewer->role?->slug === 'super_admin') {
            $handler = $ticket->assignedAdmin?->name ?? 'another admin';

            return "View-only. Reassign this chat to yourself to reply. Currently handled by {$handler}.";
        }

        if ($handoffContext['is_former']) {
            return 'This chat was reassigned. You can read history up to the handoff but cannot send new messages or see later replies.';
        }

        return null;
    }

    /**
     * @param  Collection<int, SupportTicket>  $tickets
     * @return list<array<string, mixed>>
     */
    private function mapTicketsForAdminQueue(Collection $tickets, User $admin): array
    {
        if ($tickets->isEmpty()) {
            return [];
        }

        $ids = $tickets->pluck('id')->map(fn ($id) => (int) $id)->all();
        $unreadMap = $this->batchAdminUnreadCounts($ids);
        $previewMap = $this->batchLastMessageBodies($ids);

        return $tickets
            ->map(fn (SupportTicket $ticket) => $this->ticketQueuePayload(
                $ticket,
                $admin,
                (int) ($unreadMap[$ticket->id] ?? 0),
                isset($previewMap[$ticket->id]) ? Str::limit((string) $previewMap[$ticket->id], 80) : null,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $ticketIds
     * @return array<int, int>
     */
    private function batchAdminUnreadCounts(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        return DB::table('support_ticket_messages as m')
            ->join('support_tickets as t', 't.id', '=', 'm.support_ticket_id')
            ->whereIn('m.support_ticket_id', $ticketIds)
            ->where('m.sender_type', '!=', 'admin')
            ->whereColumn('m.id', '>', 't.admin_last_read_message_id')
            ->groupBy('m.support_ticket_id')
            ->selectRaw('m.support_ticket_id as ticket_id, COUNT(*) as unread_count')
            ->pluck('unread_count', 'ticket_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  list<int>  $ticketIds
     * @return array<int, string>
     */
    private function batchLastMessageBodies(array $ticketIds): array
    {
        if ($ticketIds === []) {
            return [];
        }

        $maxIds = DB::table('support_ticket_messages')
            ->select('support_ticket_id', DB::raw('MAX(id) as max_id'))
            ->whereIn('support_ticket_id', $ticketIds)
            ->groupBy('support_ticket_id')
            ->pluck('max_id', 'support_ticket_id');

        if ($maxIds->isEmpty()) {
            return [];
        }

        return SupportTicketMessage::query()
            ->whereIn('id', $maxIds->values())
            ->pluck('body', 'support_ticket_id')
            ->map(fn ($body) => (string) $body)
            ->all();
    }

    /**
     * Sidebar / queue row — no per-ticket handoff or unread queries.
     *
     * @return array<string, mixed>
     */
    private function ticketQueuePayload(
        SupportTicket $ticket,
        User $viewer,
        int $unreadCount,
        ?string $lastMessagePreview,
    ): array {
        $waitMinutes = $ticket->queued_at
            ? (int) $ticket->queued_at->diffInMinutes(now())
            : 0;

        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'category_label' => config("customer_support.categories.{$ticket->category}.label", $ticket->category),
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'chat_status' => $ticket->chat_status ?? 'queued',
            'customer' => [
                'id' => $ticket->customer?->id ?? $ticket->user_id,
                'name' => $ticket->customer_full_name ?? $ticket->customer?->name,
                'first_name' => $ticket->customer ? $this->customerFirstName($ticket->customer) : null,
                'username' => $ticket->customer_username ?? $ticket->customer?->username,
                'email' => $ticket->customer?->email,
                'verification_level' => $ticket->customer?->current_verification_level ?? $ticket->customer?->verification_tier ?? 0,
            ],
            'assigned_admin' => $ticket->assignedAdmin ? [
                'id' => $ticket->assignedAdmin->id,
                'name' => $ticket->assignedAdmin->name,
                'first_name' => $this->staffFirstName($ticket->assignedAdmin),
            ] : null,
            'unread_count' => $unreadCount,
            'wait_minutes' => $waitMinutes,
            'last_message_preview' => $lastMessagePreview,
            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            'opened_at' => $ticket->opened_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'rated' => $ticket->rated_at !== null,
            'rating_score' => $ticket->rating_score,
            'feedback_url' => null,
            'can_compose' => $this->canComposeOnTicket($ticket, $viewer),
            'is_former_assignee' => false,
            'handoff_notice' => null,
            'message_cutoff_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function adminInboxMessagePayload(
        SupportTicketMessage $message,
        User $admin,
        int $adminId,
        int $otherReadId,
    ): array {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $kind = $metadata['kind'] ?? null;
        $attachments = ChatAttachmentHelper::normalizeList($metadata['attachments'] ?? null);

        if ($kind === 'session_closed') {
            $forStaff = $this->isStaffViewer($admin);

            return [
                'id' => $message->id,
                'body' => $forStaff
                    ? (string) config('customer_support.session_closed_admin_body')
                    : $message->body,
                'visibility' => $message->visibility,
                'sender_type' => $message->sender_type,
                'kind' => 'session_closed',
                'sender' => null,
                'sender_label' => 'HustleSafe Support',
                'is_customer' => false,
                'is_admin_message' => false,
                'is_system' => true,
                'align' => 'center',
                'mine' => false,
                'attachments' => $attachments,
                'created_at' => $message->created_at?->toIso8601String(),
                'receipt_status' => null,
                'reaction_summary' => [],
            ];
        }

        $isSystem = $message->sender_type === 'system';
        $isCustomer = $message->sender_type === 'customer';
        $isInternal = $message->visibility === 'internal';
        $senderId = (int) $message->sender_user_id;
        $isMine = ! $isSystem && $senderId === $adminId;

        $reactions = $metadata['message_reactions'] ?? [];
        $reactionSummary = [];
        if (is_array($reactions) && $reactions !== []) {
            $reactionSummary = collect($reactions)
                ->groupBy('emoji')
                ->map(fn ($group, $emoji) => [
                    'emoji' => (string) $emoji,
                    'count' => $group->count(),
                    'reacted' => $group->contains(fn (array $r) => (int) ($r['user_id'] ?? 0) === $adminId),
                ])
                ->values()
                ->all();
        }

        return [
            'id' => $message->id,
            'body' => $message->body,
            'visibility' => $message->visibility,
            'sender_type' => $message->sender_type,
            'kind' => $kind,
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
            ] : null,
            'sender_label' => $isSystem ? 'HustleSafe Support' : ($isInternal ? 'Internal note' : ($isCustomer ? 'Customer' : 'Support')),
            'is_customer' => $isCustomer,
            'is_admin_message' => $message->sender_type === 'admin',
            'is_system' => $isSystem,
            'align' => $isSystem || $isInternal ? 'center' : ($isCustomer ? 'start' : 'end'),
            'mine' => $isMine,
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
            'receipt_status' => $isMine
                ? ($message->id <= $otherReadId ? 'read' : 'delivered')
                : null,
            'reaction_summary' => $reactionSummary,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesSince(SupportTicket $ticket, User $viewer, int $afterId): array
    {
        $isAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);

        $query = $ticket->messages()
            ->with('sender:id,name,username,avatar_url,role_id', 'sender.role:id,slug')
            ->where('id', '>', $afterId)
            ->orderBy('id');

        if (! $isAdmin) {
            $query->where('visibility', 'public');
        }

        $cutoff = $this->messageCutoffForViewer($ticket, $viewer);
        if ($cutoff !== null) {
            $query->where('id', '<=', $cutoff);
        }

        return $this->mapMessagesSince($ticket, $viewer, $query->get());
    }

    /**
     * Lean deltas for HTTP polling (no per-message ticket reload).
     *
     * @param  Collection<int, SupportTicketMessage>  $rows
     * @return list<array<string, mixed>>
     */
    private function mapMessagesSince(SupportTicket $ticket, User $viewer, Collection $rows): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $isAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
        $viewerId = (int) $viewer->id;
        $otherReadId = $isAdmin
            ? (int) ($ticket->user_last_read_message_id ?? 0)
            : (int) ($ticket->admin_last_read_message_id ?? 0);

        return $rows
            ->map(fn (SupportTicketMessage $m) => $this->adminInboxMessagePayload($m, $viewer, $viewerId, $otherReadId))
            ->all();
    }

    public function canComposeOnTicket(SupportTicket $ticket, User $user): bool
    {
        if ($ticket->isClosed()) {
            return false;
        }

        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        if (! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return false;
        }

        return (int) $ticket->assigned_admin_id === (int) $user->id;
    }

    public function isFormerAssignee(SupportTicket $ticket, User $user): bool
    {
        return $this->viewerHandoffContext($ticket, $user)['is_former'];
    }

    public function handoffNotice(SupportTicket $ticket, User $viewer): ?string
    {
        return $this->handoffNoticeFromContext($ticket, $viewer, $this->viewerHandoffContext($ticket, $viewer));
    }

    public function messageCutoffForViewer(SupportTicket $ticket, User $viewer): ?int
    {
        return $this->viewerHandoffContext($ticket, $viewer)['cutoff'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function queueForAdmin(User $admin, ?string $search = null, ?string $section = null, ?int $filterAdminId = null): array
    {
        return $this->queuePanelsForAdmin($admin, $search, $section, $filterAdminId)['items'];
    }

    /**
     * @return array<string, mixed>
     */
    public function queuePanelsForAdmin(User $admin, ?string $search = null, ?string $section = null, ?int $filterAdminId = null): array
    {
        $isSuper = $admin->role?->slug === 'super_admin';
        $priorityOrder = $this->liveChatPriorityOrder();

        $base = SupportTicket::query()
            ->with(['customer:id,name,email,username,avatar_url', 'assignedAdmin:id,name,email'])
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id');

        if ($search) {
            $term = '%'.trim($search).'%';
            $base->where(function ($q) use ($term): void {
                $q->where('subject', 'like', $term)
                    ->orWhere('customer_username', 'like', $term)
                    ->orWhere('customer_full_name', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        $mine = $this->mapTicketsForAdminQueue(
            (clone $base)
                ->where('assigned_admin_id', $admin->id)
                ->whereIn('chat_status', ['queued', 'active'])
                ->orderByRaw($priorityOrder)
                ->orderBy('queued_at')
                ->limit(100)
                ->get(),
            $admin,
        );

        $unassigned = $this->mapTicketsForAdminQueue(
            (clone $base)
                ->where('chat_status', 'queued')
                ->whereNull('assigned_admin_id')
                ->orderByRaw($priorityOrder)
                ->orderBy('queued_at')
                ->limit(100)
                ->get(),
            $admin,
        );

        $teamActive = $isSuper
            ? $this->mapTicketsForAdminQueue(
                (clone $base)
                    ->where('chat_status', 'active')
                    ->when($filterAdminId, fn ($q) => $q->where('assigned_admin_id', $filterAdminId))
                    ->orderByRaw($priorityOrder)
                    ->orderByDesc('last_activity_at')
                    ->limit(150)
                    ->get(),
                $admin,
            )
            : [];

        $allOpen = $isSuper
            ? $this->mapTicketsForAdminQueue(
                (clone $base)
                    ->whereIn('chat_status', ['queued', 'active'])
                    ->when($filterAdminId, fn ($q) => $q->where('assigned_admin_id', $filterAdminId))
                    ->orderByRaw($priorityOrder)
                    ->orderByDesc('last_activity_at')
                    ->limit(200)
                    ->get(),
                $admin,
            )
            : [];

        $pool = ! $isSuper
            ? $this->mapTicketsForAdminQueue(
                (clone $base)
                    ->where(function ($q) use ($admin): void {
                        $q->where('assigned_admin_id', $admin->id)
                            ->orWhere(fn ($inner) => $inner->where('chat_status', 'queued')->whereNull('assigned_admin_id'));
                    })
                    ->whereIn('chat_status', ['queued', 'active'])
                    ->orderByRaw($priorityOrder)
                    ->orderBy('queued_at')
                    ->limit(200)
                    ->get(),
                $admin,
            )
            : [];

        $allLive = ! $isSuper
            ? $this->mapTicketsForAdminQueue(
                (clone $base)
                    ->whereIn('chat_status', ['queued', 'active'])
                    ->orderByRaw($priorityOrder)
                    ->orderByDesc('last_activity_at')
                    ->limit(200)
                    ->get(),
                $admin,
            )
            : [];

        $sections = $isSuper
            ? [
                ['key' => 'mine', 'label' => 'Assigned to me', 'count' => count($mine), 'items' => $mine],
                ['key' => 'unassigned', 'label' => 'Unassigned queue', 'count' => count($unassigned), 'items' => $unassigned],
                ['key' => 'active', 'label' => 'All active', 'count' => count($teamActive), 'items' => $teamActive],
                ['key' => 'all', 'label' => 'All open', 'count' => count($allOpen), 'items' => $allOpen],
            ]
            : [
                ['key' => 'all', 'label' => 'All open chats', 'count' => count($allLive), 'items' => $allLive],
                ['key' => 'mine', 'label' => 'My chats', 'count' => count($mine), 'items' => $mine],
                ['key' => 'pool', 'label' => 'Unassigned pool', 'count' => count($pool), 'items' => $pool],
            ];

        $activeSection = $section ?? ($isSuper ? 'all' : 'mine');
        $matched = collect($sections)->firstWhere('key', $activeSection);
        $items = is_array($matched) ? ($matched['items'] ?? $mine) : $mine;

        return [
            'sections' => $sections,
            'items' => $items,
            'active_section' => $activeSection,
            'is_super_admin' => $isSuper,
            'unread_total' => $this->unreadSupportChatsForAdmin($admin),
        ];
    }

    /**
     * @return array{recent: list<array{date: string, label: string, sessions: list<array<string, mixed>>}>, archived: list<array{date: string, label: string, sessions: list<array<string, mixed>>}>, retention_days: int}
     */
    public function conversationHistoryGrouped(User $viewer, ?string $search = null, ?int $filterAdminId = null): array
    {
        $retentionDays = (int) config('customer_support.history_retention_days', 30);
        $archiveCutoff = now()->subDays($retentionDays);
        $isSuper = $viewer->role?->slug === 'super_admin';

        $query = SupportTicket::query()
            ->with(['customer:id,name,email,username,avatar_url', 'assignedAdmin:id,name,email'])
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id')
            ->where('chat_status', 'closed')
            ->whereNotNull('closed_at');

        if ($isSuper) {
            if ($filterAdminId) {
                $query->where('assigned_admin_id', $filterAdminId);
            }
        } else {
            $query->where(function ($q) use ($viewer): void {
                $q->where('assigned_admin_id', $viewer->id)
                    ->orWhereHas('handoffs', fn ($h) => $h->where('from_admin_id', $viewer->id));
            });
        }

        if ($search) {
            $term = '%'.trim($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->where('subject', 'like', $term)
                    ->orWhere('customer_username', 'like', $term)
                    ->orWhere('customer_full_name', 'like', $term)
                    ->orWhereHas('customer', fn ($c) => $c->where('email', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        $tickets = $query
            ->orderByDesc('closed_at')
            ->limit(400)
            ->get();

        $recentBuckets = [];
        $archivedBuckets = [];
        $detailPayloads = $this->mapTicketsForAdminDetailList($tickets, $viewer);

        foreach ($tickets as $index => $ticket) {
            $closedAt = $ticket->closed_at ?? $ticket->updated_at;
            if ($closedAt === null) {
                continue;
            }

            $payload = $detailPayloads[$index] ?? $this->ticketDetailPayload($ticket, $viewer);
            $dateKey = $closedAt->toDateString();
            $isRecent = $closedAt->gte($archiveCutoff);

            if ($isRecent) {
                if (! isset($recentBuckets[$dateKey])) {
                    $recentBuckets[$dateKey] = [
                        'date' => $dateKey,
                        'label' => $this->historyDayLabel($closedAt),
                        'sessions' => [],
                    ];
                }
                $recentBuckets[$dateKey]['sessions'][] = $payload;
            } else {
                if (! isset($archivedBuckets[$dateKey])) {
                    $archivedBuckets[$dateKey] = [
                        'date' => $dateKey,
                        'label' => $this->historyDayLabel($closedAt),
                        'sessions' => [],
                    ];
                }
                $archivedBuckets[$dateKey]['sessions'][] = $payload;
            }
        }

        $sortGroups = static function (array $buckets): array {
            krsort($buckets);

            return array_values($buckets);
        };

        return [
            'recent' => $sortGroups($recentBuckets),
            'archived' => $sortGroups($archivedBuckets),
            'retention_days' => $retentionDays,
        ];
    }

    private function historyDayLabel(\Illuminate\Support\Carbon $date): string
    {
        if ($date->isToday()) {
            return 'Today';
        }

        if ($date->isYesterday()) {
            return 'Yesterday';
        }

        return $date->format('l, j M Y');
    }

    public function reconcileStaffSupportNotifications(User $staff): int
    {
        if (! in_array($staff->role?->slug, ['admin', 'super_admin'], true)) {
            return 0;
        }

        $cleared = 0;
        $query = SupportTicket::query()
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id');

        if ($staff->role?->slug !== 'super_admin') {
            $query->where('assigned_admin_id', $staff->id);
        }

        $tickets = $query->limit(200)->get(['id']);
        $unreadMap = $this->batchAdminUnreadCounts($tickets->pluck('id')->map(fn ($id) => (int) $id)->all());

        foreach ($tickets as $ticket) {
            if (($unreadMap[$ticket->id] ?? 0) > 0) {
                continue;
            }

            $cleared += $this->notificationCentre->markCustomerSupportNotificationsRead($staff, (int) $ticket->id);
        }

        return $cleared;
    }

    public function unreadSupportChatsForAdmin(User $admin): int
    {
        if ($admin->role?->slug === 'super_admin') {
            return 0;
        }

        $query = SupportTicket::query()
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id')
            ->whereIn('chat_status', ['queued', 'active'])
            ->where('assigned_admin_id', $admin->id);

        $ids = $query->pluck('id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return 0;
        }

        return (int) array_sum($this->batchAdminUnreadCounts($ids));
    }

    public function userChats(User $customer): Collection
    {
        return SupportTicket::query()
            ->where('user_id', $customer->id)
            ->whereNull('opened_by_admin_id')
            ->latest('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (SupportTicket $t) => $this->ticketListPayload($t, $customer));
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetBootstrap(User $customer): array
    {
        $active = SupportTicket::query()
            ->where('user_id', $customer->id)
            ->whereNull('opened_by_admin_id')
            ->whereIn('chat_status', ['queued', 'active'])
            ->latest('updated_at')
            ->first();

        return [
            'enabled' => true,
            'categories' => $this->categories(),
            'active_ticket' => $active ? $this->ticketListPayload($active, $customer) : null,
            'recent_chats' => $this->userChats($customer)->take(5)->values()->all(),
            'unread_total' => $this->unreadTotalForUser($customer),
            'closure_reactions' => $this->closureReactions(),
            'feedback_survey' => $this->feedbackSurveySteps(),
        ];
    }

    public function unreadTotalForUser(User $customer): int
    {
        return (int) SupportTicket::query()
            ->where('user_id', $customer->id)
            ->whereNull('opened_by_admin_id')
            ->whereIn('chat_status', ['queued', 'active'])
            ->get()
            ->sum(fn (SupportTicket $ticket) => $this->ticketListPayload($ticket, $customer)['unread_count'] ?? 0);
    }

    /**
     * @return array{ticket: array<string, mixed>, messages: list<array<string, mixed>>}
     */
    public function openTicketForUser(SupportTicket $ticket, User $customer): array
    {
        $messages = $this->messages($ticket, $customer);
        $this->markRead($ticket, $customer);

        return [
            'ticket' => $this->ticketListPayload($ticket->fresh(), $customer),
            'messages' => $messages['items'],
            'has_more' => $messages['has_more'],
        ];
    }

    public function userContext(User $user, ?User $viewer = null): array
    {
        $user->loadMissing('role');
        $viewer ??= $user;
        $viewer->loadMissing('role');
        $staffOps = $viewer->role?->slug === 'admin';

        $questColumns = ['id', 'slug', 'uuid', 'title', 'reference_code', 'status', 'escrow_status'];

        $activeQuests = Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->whereNotIn('status', ['completed', 'cancelled', 'archived'])
            ->latest()
            ->limit(8)
            ->get($questColumns);

        $proposals = QuestOffer::query()
            ->where('freelancer_id', $user->id)
            ->latest()
            ->limit(8)
            ->with('quest:id,slug,uuid,title,reference_code')
            ->get(['id', 'quest_id', 'status', 'created_at']);

        $contracts = Quest::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->whereNotNull('accepted_quest_offer_id')
            ->latest()
            ->limit(8)
            ->get($questColumns);

        $disputes = QuestDispute::query()
            ->whereHas('quest', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('client_id', $user->id)
                ->orWhere('freelancer_id', $user->id)))
            ->latest()
            ->limit(8)
            ->with('quest:id,title,reference_code')
            ->get(['id', 'quest_id', 'status', 'created_at']);

        $payments = AdminFinancialLedgerEntry::query()
            ->where(fn ($q) => $q->where('client_id', $user->id)->orWhere('freelancer_id', $user->id))
            ->latest('occurred_at')
            ->limit(8)
            ->get(['id', 'type', 'status', 'gross_amount_minor', 'net_amount_minor', 'occurred_at']);

        $previousTickets = SupportTicket::query()
            ->where('user_id', $user->id)
            ->whereNull('opened_by_admin_id')
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'uuid', 'subject', 'category', 'chat_status', 'status', 'assigned_admin_id', 'created_at', 'closed_at']);

        $kycCases = Schema::hasTable('kyc_review_cases')
            ? KycReviewCase::query()
                ->where('user_id', $user->id)
                ->latest('entered_queue_at')
                ->limit(8)
                ->get(['id', 'uuid', 'status', 'target_tier', 'verification_type', 'priority', 'entered_queue_at'])
            : collect();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'verification_level' => $user->current_verification_level ?? $user->verification_tier ?? 0,
                'avatar_url' => $user->avatar_url,
                'profile_url' => $staffOps
                    ? $this->safeRoute('operations.users.index', ['search' => $user->email ?? $user->username])
                    : $this->safeRoute('admin.users.profile', $user),
                'kyc_url' => $staffOps
                    ? $this->safeRoute('operations.verifications.index')
                    : $this->safeRoute('admin.kyc.index', ['user_id' => $user->id]),
                'verification_url' => $staffOps
                    ? $this->safeRoute('operations.verifications.index')
                    : $this->safeRoute('admin.verification-engine.index'),
                'financial_url' => $staffOps
                    ? $this->safeRoute('operations.payments.index')
                    : $this->safeRoute('admin.financial.index'),
            ],
            'active_quests' => $activeQuests->map(fn (Quest $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'reference_code' => $q->reference_code,
                'status' => $q->status?->value ?? (string) $q->status,
                'escrow_status' => $q->escrow_status,
                'url' => $this->questAdminUrl($q, $staffOps),
            ])->values()->all(),
            'proposals' => $proposals->map(fn (QuestOffer $o) => [
                'id' => $o->id,
                'quest_id' => $o->quest_id,
                'quest' => $o->quest?->title,
                'reference_code' => $o->quest?->reference_code,
                'status' => $o->status?->value ?? (string) $o->status,
                'created_at' => $o->created_at?->toIso8601String(),
                'url' => $this->safeRoute('admin.proposals.detail', ['proposal' => $o->id]),
            ])->values()->all(),
            'contracts' => $contracts->map(fn (Quest $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'reference_code' => $q->reference_code,
                'status' => $q->status?->value ?? (string) $q->status,
                'escrow_status' => $q->escrow_status,
                'url' => $this->questAdminUrl($q, $staffOps),
                'ledger_url' => $this->safeRoute('admin.financial.escrows.ledger', ['quest' => $q->id]),
            ])->values()->all(),
            'disputes' => $disputes->map(fn (QuestDispute $d) => [
                'id' => $d->id,
                'quest_id' => $d->quest_id,
                'quest' => $d->quest?->title,
                'reference_code' => $d->quest?->reference_code,
                'status' => $d->status?->value ?? (string) $d->status,
                'created_at' => $d->created_at?->toIso8601String(),
                'url' => $staffOps
                    ? $this->safeRoute('operations.disputes.index', ['search' => $d->quest?->reference_code ?? $d->id])
                    : $this->safeRoute('admin.disputes.index', ['search' => $d->quest?->reference_code ?? $d->id]),
            ])->values()->all(),
            'payments' => $payments->map(fn ($p) => [
                'id' => $p->id,
                'type' => $p->type,
                'amount_minor' => $p->gross_amount_minor ?? $p->net_amount_minor,
                'status' => $p->status,
                'occurred_at' => $p->occurred_at?->toIso8601String(),
                'url' => $staffOps
                    ? $this->safeRoute('operations.payments.index')
                    : $this->safeRoute('admin.financial.index'),
            ])->values()->all(),
            'verifications' => $user->userVerifications()
                ->latest()
                ->limit(8)
                ->get(['id', 'category', 'verification_type', 'status', 'submitted_at'])
                ->map(fn ($v) => [
                    'id' => $v->id,
                    'type' => $v->verification_type ?: $v->category?->value,
                    'status' => $v->status?->value ?? (string) $v->status,
                    'submitted_at' => $v->submitted_at?->toIso8601String(),
                    'url' => $staffOps
                        ? $this->safeRoute('operations.verifications.index')
                        : $this->safeRoute('admin.verification-engine.index'),
                ])->values()->all(),
            'kyc_cases' => $kycCases->map(fn (KycReviewCase $case) => [
                'id' => $case->id,
                'uuid' => $case->uuid,
                'status' => $case->status,
                'target_tier' => $case->target_tier,
                'verification_type' => $case->verification_type,
                'priority' => $case->priority,
                'entered_queue_at' => $case->entered_queue_at?->toIso8601String(),
                'url' => $this->safeRoute('admin.kyc.cases.show', $case),
            ])->values()->all(),
            'previous_support_chats' => $previousTickets->map(fn (SupportTicket $t) => [
                'id' => $t->id,
                'uuid' => $t->uuid,
                'subject' => $t->subject,
                'category' => $t->category,
                'chat_status' => $t->chat_status,
                'status' => $t->status,
                'created_at' => $t->created_at?->toIso8601String(),
                'closed_at' => $t->closed_at?->toIso8601String(),
                'url' => $staffOps
                    ? $this->safeRoute('operations.customer-support.index', ['ticket' => $t->uuid])
                    : $this->safeRoute('admin.customer-support.index', ['ticket' => $t->uuid]),
            ])->values()->all(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function searchConversations(User $admin, ?string $q, int $perPage = 25): LengthAwarePaginator
    {
        $query = SupportTicket::query()
            ->with(['customer:id,name,email', 'assignedAdmin:id,name'])
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id');

        if ($q) {
            $term = '%'.trim($q).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('subject', 'like', $term)
                    ->orWhere('uuid', 'like', $term)
                    ->orWhere('customer_username', 'like', $term)
                    ->orWhere('customer_full_name', 'like', $term)
                    ->orWhereHas('messages', fn ($m) => $m->where('body', 'like', $term));
            });
        }

        return $query->latest('updated_at')->paginate($perPage)->through(
            fn (SupportTicket $t) => $this->ticketListPayload($t, $admin),
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function performanceMetrics(): array
    {
        $admins = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $now = now();

        return $admins->map(function (User $admin) use ($now): array {
            $base = SupportTicket::query()
                ->where('assigned_admin_id', $admin->id)
                ->whereNotNull('user_id')
                ->whereNull('opened_by_admin_id');

            $closed = (clone $base)->where('chat_status', 'closed');
            $rated = (clone $closed)->whereNotNull('rated_at')->whereNotNull('rating_score');

            $avgRating = (clone $rated)->avg('rating_score');
            $avgResolution = (clone $closed)->whereNotNull('resolution_seconds')->avg('resolution_seconds');

            $liveToday = (clone $base)
                ->whereIn('chat_status', ['queued', 'active'])
                ->where('opened_at', '>=', $now->copy()->startOfDay())
                ->count();

            $closedToday = (clone $closed)
                ->where('closed_at', '>=', $now->copy()->startOfDay())
                ->count();

            return [
                'admin_id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'chats_today' => (clone $base)->where('opened_at', '>=', $now->copy()->startOfDay())->count(),
                'chats_week' => (clone $base)->where('opened_at', '>=', $now->copy()->startOfWeek())->count(),
                'chats_month' => (clone $base)->where('opened_at', '>=', $now->copy()->startOfMonth())->count(),
                'live_today' => $liveToday,
                'closed_today' => $closedToday,
                'total_chats' => (clone $base)->count(),
                'closed_chats' => $closed->count(),
                'average_rating' => $avgRating !== null ? round((float) $avgRating, 1) : null,
                'sessions_rated' => $rated->count(),
                'average_resolution_minutes' => $avgResolution ? (int) round($avgResolution / 60) : null,
                'online' => $this->onlineAdminIds()->contains($admin->id),
            ];
        })->all();
    }

    /**
     * @return array{admin: array<string, mixed>, feedback: list<array<string, mixed>>}
     */
    public function adminPerformanceFeedbackDetail(int $adminUserId): array
    {
        $admin = User::query()->findOrFail($adminUserId);
        $survey = collect($this->feedbackSurveySteps())->keyBy('id');

        $tickets = SupportTicket::query()
            ->with('customer:id,name,username')
            ->where('assigned_admin_id', $adminUserId)
            ->whereNotNull('user_id')
            ->whereNull('opened_by_admin_id')
            ->whereNotNull('rated_at')
            ->whereNotNull('rating_score')
            ->orderByDesc('rated_at')
            ->limit(200)
            ->get();

        $feedback = $tickets->map(function (SupportTicket $ticket) use ($survey): array {
            $answers = [];
            foreach ((array) ($ticket->feedback_answers ?? []) as $key => $value) {
                $step = $survey->get($key);
                $label = collect($step['options'] ?? [])->firstWhere('value', $value)['label'] ?? (string) $value;
                $answers[] = [
                    'id' => (string) $key,
                    'question' => $step['question'] ?? (string) $key,
                    'answer' => $label,
                ];
            }

            $reactionEmoji = collect($this->closureReactions())->firstWhere('key', $ticket->rating_reaction)['emoji'] ?? null;

            return [
                'ticket_id' => $ticket->id,
                'subject' => $ticket->subject,
                'customer_name' => $ticket->customer?->name,
                'customer_username' => $ticket->customer?->username,
                'rated_at' => $ticket->rated_at?->toIso8601String(),
                'rating_score' => (int) $ticket->rating_score,
                'reaction' => $ticket->rating_reaction,
                'reaction_emoji' => $reactionEmoji,
                'comment' => $ticket->rating_comment,
                'answers' => $answers,
            ];
        })->values()->all();

        $avg = $tickets->avg('rating_score');

        return [
            'admin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'average_rating' => $avg !== null ? round((float) $avg, 1) : null,
                'sessions_rated' => $tickets->count(),
            ],
            'feedback' => $feedback,
        ];
    }

    public function ratingUrl(SupportTicket $ticket): string
    {
        return URL::temporarySignedRoute(
            'support.rate.show',
            now()->addDays(14),
            ['ticket' => $ticket->uuid],
        );
    }

    public function canAccessTicket(SupportTicket $ticket, User $user): bool
    {
        if ((int) $ticket->user_id === (int) $user->id) {
            return true;
        }

        if (! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            return false;
        }

        if ($user->role?->slug === 'super_admin') {
            return true;
        }

        if ($user->role?->slug === 'admin') {
            if ($ticket->opened_by_admin_id !== null) {
                return false;
            }

            if ($ticket->isClosed()) {
                return (int) $ticket->assigned_admin_id === (int) $user->id
                    || $this->isFormerAssignee($ticket, $user);
            }

            return true;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function ticketListPayload(
        SupportTicket $ticket,
        User $viewer,
        ?int $unreadCount = null,
        ?SupportTicketMessage $lastMessage = null,
        ?string $lastMessagePreview = null,
    ): array {
        $isAdmin = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
        $readField = $isAdmin ? 'admin_last_read_message_id' : 'user_last_read_message_id';
        $lastRead = (int) ($ticket->{$readField} ?? 0);

        if ($unreadCount === null) {
            $unreadCount = $ticket->messages()
                ->when(! $isAdmin, fn ($q) => $q->where('visibility', 'public'))
                ->when($isAdmin, fn ($q) => $q->where('sender_type', '!=', 'admin'))
                ->where('id', '>', $lastRead)
                ->count();
        }

        if ($lastMessage === null && $lastMessagePreview === null) {
            $lastMessage = $ticket->messages()->latest('id')->first();
        }

        $waitMinutes = $ticket->queued_at
            ? (int) $ticket->queued_at->diffInMinutes(now())
            : 0;

        return [
            'id' => $ticket->id,
            'uuid' => $ticket->uuid,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'category_label' => config("customer_support.categories.{$ticket->category}.label", $ticket->category),
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'chat_status' => $ticket->chat_status ?? 'queued',
            'customer' => [
                'id' => $ticket->customer?->id ?? $ticket->user_id,
                'name' => $ticket->customer_full_name ?? $ticket->customer?->name,
                'first_name' => $ticket->customer ? $this->customerFirstName($ticket->customer) : null,
                'username' => $ticket->customer_username ?? $ticket->customer?->username,
                'email' => $ticket->customer?->email,
                'verification_level' => $ticket->customer?->current_verification_level ?? $ticket->customer?->verification_tier ?? 0,
            ],
            'assigned_admin' => $ticket->assignedAdmin ? [
                'id' => $ticket->assignedAdmin->id,
                'name' => $ticket->assignedAdmin->name,
                'first_name' => $this->staffFirstName($ticket->assignedAdmin),
            ] : null,
            'unread_count' => $unreadCount,
            'wait_minutes' => $waitMinutes,
            'last_message_preview' => $lastMessagePreview !== null
                ? Str::limit($lastMessagePreview, 80)
                : ($lastMessage ? Str::limit($lastMessage->body, 80) : null),
            'last_activity_at' => $ticket->last_activity_at?->toIso8601String(),
            'opened_at' => $ticket->opened_at?->toIso8601String(),
            'closed_at' => $ticket->closed_at?->toIso8601String(),
            'rated' => $ticket->rated_at !== null,
            'rating_score' => $ticket->rating_score,
            'feedback_url' => $ticket->isClosed() && $ticket->rated_at === null && $ticket->isLiveChat()
                ? $this->ratingUrl($ticket)
                : null,
            'can_compose' => $this->canComposeOnTicket($ticket, $viewer),
            'is_former_assignee' => ($handoff = $this->viewerHandoffContext($ticket, $viewer))['is_former'],
            'handoff_notice' => $this->handoffNoticeFromContext($ticket, $viewer, $handoff),
            'message_cutoff_id' => $handoff['cutoff'],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $attachments
     */
    private function createMessage(
        SupportTicket $ticket,
        User $sender,
        string $senderType,
        string $body,
        array $attachments,
        string $visibility,
    ): SupportTicketMessage {
        return $ticket->messages()->create([
            'sender_user_id' => $sender->id,
            'sender_type' => $senderType,
            'visibility' => $visibility,
            'body' => $body,
            'metadata' => $attachments !== [] ? ['attachments' => $attachments] : null,
        ]);
    }

    /**
     * WebSocket payload shared by customer and staff (no viewer-specific mine/align).
     *
     * @return array<string, mixed>
     */
    private function messageBroadcastPayload(SupportTicketMessage $message): array
    {
        $message->loadMissing('sender');
        $attachments = ChatAttachmentHelper::normalizeList($message->metadata['attachments'] ?? null);
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $kind = $metadata['kind'] ?? null;

        if ($kind === 'session_closed') {
            return $this->sessionClosedBroadcastPayload($message);
        }

        $isSystem = $message->sender_type === 'system';
        $isCustomer = $message->sender_type === 'customer';
        $isInternal = $message->visibility === 'internal';

        return [
            'id' => $message->id,
            'body' => $message->body,
            'visibility' => $message->visibility,
            'sender_type' => $message->sender_type,
            'kind' => $kind,
            'feedback_url' => $metadata['feedback_url'] ?? null,
            'reactions' => $metadata['reactions'] ?? null,
            'reaction_summary' => $this->reactionSummaryForBroadcast($message),
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'first_name' => $this->staffFirstName($message->sender),
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
            ] : null,
            'sender_label' => $this->messageSenderLabel($message),
            'is_customer' => $isCustomer,
            'is_admin_message' => $message->sender_type === 'admin',
            'is_system' => $isSystem,
            'align' => $isSystem || $isInternal ? 'center' : ($isCustomer ? 'start' : 'end'),
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(SupportTicketMessage $message, User $viewer, ?SupportTicket $ticket = null): array
    {
        $attachments = ChatAttachmentHelper::normalizeList($message->metadata['attachments'] ?? null);
        $senderId = (int) $message->sender_user_id;

        $isSystem = $message->sender_type === 'system';
        $isCustomer = $message->sender_type === 'customer';
        $isInternal = $message->visibility === 'internal';
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $kind = $metadata['kind'] ?? null;

        if ($kind === 'session_closed') {
            return $this->sessionClosedPayloadForViewer($message, $viewer, $metadata, $attachments);
        }

        if ($ticket === null) {
            $message->loadMissing('ticket');
            $ticket = $message->ticket;
        }
        abort_if($ticket === null, 404);

        $payload = $this->messageBroadcastPayload($message);
        $payload['mine'] = ! $isSystem && $senderId === (int) $viewer->id;
        $payload['receipt_status'] = $this->receiptStatusForMessage($message, $ticket, $viewer);
        $payload['reaction_summary'] = $this->reactionSummary($message, $viewer);

        return $payload;
    }

    public function reactToMessage(SupportTicket $ticket, SupportTicketMessage $message, User $user, string $emoji): array
    {
        abort_unless($this->canAccessTicket($ticket, $user), 403);
        abort_if($message->support_ticket_id !== $ticket->id, 404);

        $allowed = config('customer_support.message_reactions', ['👍', '❤️', '😂', '😮', '🙏', '🎉']);
        if (! in_array($emoji, $allowed, true)) {
            throw ValidationException::withMessages(['emoji' => __('Reaction not allowed.')]);
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $reactions = collect($metadata['message_reactions'] ?? []);
        $existing = $reactions->first(fn (array $r) => (int) ($r['user_id'] ?? 0) === (int) $user->id && ($r['emoji'] ?? '') === $emoji);

        if ($existing) {
            $reactions = $reactions->reject(fn (array $r) => (int) ($r['user_id'] ?? 0) === (int) $user->id && ($r['emoji'] ?? '') === $emoji);
        } else {
            $reactions->push([
                'user_id' => $user->id,
                'emoji' => $emoji,
                'name' => $user->name,
            ]);
        }

        $metadata['message_reactions'] = $reactions->values()->all();
        $message->forceFill(['metadata' => $metadata])->save();
        $message->loadMissing('sender');

        $payload = $this->messageBroadcastPayload($message);
        $this->broadcastMessage($ticket, $payload);

        return $this->messagePayload($message, $user);
    }

    /**
     * @return list<array{emoji: string, count: int}>
     */
    private function reactionSummaryForBroadcast(SupportTicketMessage $message): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];

        return collect($metadata['message_reactions'] ?? [])
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $group->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{emoji: string, count: int, reacted: bool}>
     */
    private function reactionSummary(SupportTicketMessage $message, User $viewer): array
    {
        $metadata = is_array($message->metadata) ? $message->metadata : [];
        $raw = collect($metadata['message_reactions'] ?? []);

        return $raw
            ->groupBy('emoji')
            ->map(fn ($group, $emoji) => [
                'emoji' => (string) $emoji,
                'count' => $group->count(),
                'reacted' => $group->contains(fn (array $r) => (int) ($r['user_id'] ?? 0) === (int) $viewer->id),
            ])
            ->values()
            ->all();
    }

    private function receiptStatusForMessage(SupportTicketMessage $message, SupportTicket $ticket, User $viewer): ?string
    {
        if ($message->sender_type === 'system') {
            return null;
        }

        $isMine = (int) $message->sender_user_id === (int) $viewer->id;
        if (! $isMine) {
            return null;
        }

        $isAdminViewer = in_array($viewer->role?->slug, ['admin', 'super_admin'], true);
        $otherReadId = $isAdminViewer
            ? (int) ($ticket->user_last_read_message_id ?? 0)
            : (int) ($ticket->admin_last_read_message_id ?? 0);

        if ($message->id <= $otherReadId) {
            return 'read';
        }

        return 'delivered';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<array<string, mixed>>  $attachments
     * @return array<string, mixed>
     */
    private function sessionClosedPayloadForViewer(
        SupportTicketMessage $message,
        User $viewer,
        array $metadata,
        array $attachments,
    ): array {
        $forStaff = $this->isStaffViewer($viewer);

        return [
            'id' => $message->id,
            'body' => $forStaff
                ? (string) config('customer_support.session_closed_admin_body')
                : $message->body,
            'admin_body' => $forStaff ? null : (string) config('customer_support.session_closed_admin_body'),
            'visibility' => $message->visibility,
            'sender_type' => $message->sender_type,
            'kind' => 'session_closed',
            'feedback_url' => $forStaff ? null : ($metadata['feedback_url'] ?? null),
            'reactions' => $forStaff ? null : ($metadata['reactions'] ?? []),
            'sender' => $message->sender ? [
                'id' => $message->sender->id,
                'name' => $message->sender->name,
                'first_name' => $this->staffFirstName($message->sender),
                'username' => $message->sender->username,
                'avatar_url' => $message->sender->avatar_url,
            ] : null,
            'sender_label' => 'HustleSafe Support',
            'is_customer' => false,
            'is_admin_message' => false,
            'is_system' => true,
            'align' => 'center',
            'mine' => false,
            'attachments' => $attachments,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function messageSenderLabel(SupportTicketMessage $message): string
    {
        if ($message->sender_type === 'system') {
            return 'HustleSafe Support';
        }

        if ($message->visibility === 'internal') {
            return 'Internal note';
        }

        if ($message->sender_type === 'customer') {
            $name = $message->sender?->name ?? 'Customer';

            return "Customer · {$name}";
        }

        $first = $message->sender ? $this->staffFirstName($message->sender) : 'Support';

        return "Customer Support · {$first}";
    }

    /**
     * @return array{agent_signature: string, opening: list<array{id: string, label: string, body: string}>, closing: list<array{id: string, label: string, body: string, ends_session: bool}>}
     */
    public function messageTemplatesPayload(User $admin): array
    {
        $signature = 'Customer Manager '.$this->staffFirstName($admin);
        $configured = config('customer_support.message_templates', []);

        $opening = [];
        foreach ($configured['opening'] ?? [] as $template) {
            if (! is_array($template) || empty($template['id'])) {
                continue;
            }
            $opening[] = [
                'id' => (string) $template['id'],
                'label' => (string) ($template['label'] ?? 'Opening'),
                'body' => (string) ($template['body'] ?? ''),
            ];
        }

        $closing = [];
        foreach ($configured['closing'] ?? [] as $template) {
            if (! is_array($template) || empty($template['id'])) {
                continue;
            }
            $closing[] = [
                'id' => (string) $template['id'],
                'label' => (string) ($template['label'] ?? 'Closing'),
                'body' => (string) ($template['body'] ?? ''),
                'ends_session' => (bool) ($template['ends_session'] ?? true),
            ];
        }

        return [
            'agent_signature' => $signature,
            'opening' => $opening,
            'closing' => $closing,
        ];
    }

    public function customerFirstName(?User $customer): string
    {
        if (! $customer) {
            return 'there';
        }

        if (filled($customer->first_name)) {
            return trim((string) $customer->first_name);
        }

        $name = trim((string) $customer->name);

        return $name !== '' ? explode(' ', $name, 2)[0] : 'there';
    }

    private function staffFirstName(User $user): string
    {
        if (filled($user->first_name)) {
            return trim((string) $user->first_name);
        }

        $name = trim((string) $user->name);

        return $name !== '' ? explode(' ', $name, 2)[0] : 'Support';
    }

    /**
     * @param  list<UploadedFile>|null  $files
     * @return list<array<string, mixed>>
     */
    public function broadcastSessionUpdate(SupportTicket $ticket, User $viewer): void
    {
        if (! $this->liveBroadcastEnabled()) {
            return;
        }

        try {
            broadcast(new CustomerSupportSessionUpdated(
                $ticket->id,
                $this->ticketListPayload($ticket, $viewer),
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function broadcastQueueChanged(SupportTicket $ticket, string $action = 'updated'): void
    {
        if (! $this->liveBroadcastEnabled()) {
            return;
        }

        $ticket->loadMissing(['customer', 'assignedAdmin']);

        $viewer = $ticket->assignedAdmin
            ?? User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();

        if ($viewer === null) {
            return;
        }

        try {
            broadcast(new CustomerSupportQueueChanged(
                $this->ticketListPayload($ticket, $viewer),
                $action,
            ));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function liveChatPriorityOrder(): string
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            return "FIELD(priority, 'urgent', 'high', 'medium', 'low')";
        }

        return "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 5 END";
    }

    private function safeRoute(string $name, mixed $parameters = []): ?string
    {
        try {
            return route($name, $parameters);
        } catch (\Throwable) {
            return null;
        }
    }

    private function questAdminUrl(Quest $quest, bool $staffOps): ?string
    {
        $key = $quest->getRouteKey();
        if ($key === null || $key === '') {
            return $staffOps
                ? $this->safeRoute('operations.moderation.index')
                : $this->safeRoute('admin.quests.index');
        }

        return $this->safeRoute('admin.quests.detail', ['quest' => $key]);
    }

    private function storeAttachments(?array $files): array
    {
        if ($files === null || $files === []) {
            return [];
        }

        $max = (int) config('customer_support.max_attachments', 5);
        $stored = [];

        foreach (array_slice($files, 0, $max) as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $path = $file->store('support-chat/'.date('Y/m'), 'public');
            $stored[] = ChatAttachmentHelper::normalizeOne([
                'path' => $path,
                'name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'type' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'image' : 'file',
            ]);
        }

        return $stored;
    }
}
