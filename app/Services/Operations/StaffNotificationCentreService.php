<?php

namespace App\Services\Operations;

use App\Models\AdminNotification;
use App\Models\AdminTask;
use App\Models\QuestDispute;
use App\Models\StaffNotificationPreference;
use App\Models\AdminDirectConversation;
use App\Models\AdminDirectMessage;
use App\Models\StaffTeamChatMessage;
use App\Models\StaffTeamChatRoom;
use App\Models\SupportChatAssignment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\MessagingViewPresence;
use App\Models\UserVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class StaffNotificationCentreService
{
    public function syncInbox(User $staff): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $this->syncTasks($staff);
        $this->syncDisputes($staff);
        $this->syncKyc($staff);
        $this->syncChats($staff);
    }

    public function listing(User $staff, Request $request): array
    {
        $this->syncInbox($staff);

        $category = (string) $request->input('category', '');
        $filter = (string) $request->input('filter', 'inbox');

        $query = AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->latest();

        if ($category !== '') {
            $query->where('category', $category);
        }

        match ($filter) {
            'unread' => $query->whereNull('read_at'),
            'critical' => $query->where('priority', 'critical')->whereNull('actioned_at'),
            'actioned' => $query->whereNotNull('actioned_at'),
            default => null,
        };

        $items = $query->limit(200)->get()->map(fn (AdminNotification $n) => $this->payload($n, $staff));

        return [
            'items' => $items,
            'critical_banners' => $this->criticalBanners($staff),
            'meta' => [
                'unread' => AdminNotification::query()->where('admin_user_id', $staff->id)->whereNull('read_at')->count(),
                'critical' => AdminNotification::query()->where('admin_user_id', $staff->id)->where('priority', 'critical')->whereNull('actioned_at')->count(),
            ],
            'categories' => collect(config('operations.notification_categories', []))->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ];
    }

    public function preferences(User $staff): array
    {
        $defaults = config('operations.notification_events', []);
        $stored = StaffNotificationPreference::query()->firstOrCreate(
            ['staff_user_id' => $staff->id],
            ['preferences' => []],
        );

        $prefs = [];
        foreach ($defaults as $event => $meta) {
            $prefs[$event] = [
                'in_app' => data_get($stored->preferences, "{$event}.in_app", $meta['default_in_app'] ?? true),
                'email' => data_get($stored->preferences, "{$event}.email", $meta['default_email'] ?? false),
                'category' => $meta['category'] ?? 'system',
                'label' => Str::headline(str_replace('_', ' ', $event)),
            ];
        }

        return ['events' => $prefs];
    }

    public function updatePreferences(User $staff, array $data): void
    {
        StaffNotificationPreference::query()->updateOrCreate(
            ['staff_user_id' => $staff->id],
            ['preferences' => $data['preferences'] ?? []],
        );
    }

    public function markRead(AdminNotification $notification, User $staff): void
    {
        abort_unless((int) $notification->admin_user_id === (int) $staff->id, 403);
        $notification->forceFill(['read_at' => now()])->save();
    }

    public function markTeamChatNotificationsRead(User $staff): int
    {
        if (! Schema::hasTable('admin_notifications')) {
            return 0;
        }

        return AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->where('category', 'team_chat')
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markCustomerSupportNotificationsRead(User $staff, ?int $ticketId = null): int
    {
        if (! Schema::hasTable('admin_notifications')) {
            return 0;
        }

        $query = AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->where('category', 'customer_support')
            ->whereNull('read_at');

        if ($ticketId !== null) {
            $query->where('data->ticket_id', $ticketId);
        }

        return $query->update(['read_at' => now()]);
    }

    public function markCustomerSupportNotificationsReadForTicket(int $ticketId): int
    {
        if (! Schema::hasTable('admin_notifications')) {
            return 0;
        }

        return AdminNotification::query()
            ->where('category', 'customer_support')
            ->where('data->ticket_id', $ticketId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markActioned(AdminNotification $notification, User $staff): void
    {
        abort_unless((int) $notification->admin_user_id === (int) $staff->id, 403);
        $notification->forceFill(['actioned_at' => now(), 'read_at' => $notification->read_at ?? now()])->save();
    }

    public function unreadCount(User $staff): int
    {
        if (! Schema::hasTable('admin_notifications')) {
            return 0;
        }

        return AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->whereNull('read_at')
            ->count();
    }

    public function notifyDirectMessage(
        User $sender,
        AdminDirectMessage $message,
        AdminDirectConversation $conversation,
        int $recipientId,
    ): void {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $recipient = User::query()->with('role:id,slug')->find($recipientId);
        if ($recipient === null) {
            return;
        }

        if (MessagingViewPresence::isViewing(
            MessagingViewPresence::SCOPE_ADMIN_DM,
            (int) $conversation->id,
            (int) $recipient->id,
        )) {
            return;
        }

        $preview = Str::limit(trim((string) $message->body) ?: $this->attachmentPreview($message->attachments), 140);
        $mentionedIds = collect($message->mentions ?? [])->map(fn ($id) => (int) $id)->filter()->all();
        $isMentioned = in_array((int) $recipient->id, $mentionedIds, true);
        $openUrl = $recipient->role?->slug === 'super_admin'
            ? route('admin.dashboard', ['open_messenger' => 1, 'conversation' => $conversation->id])
            : route('operations.dashboard', ['open_messenger' => 1, 'conversation' => $conversation->id]);

        AdminNotification::query()->create([
            'admin_user_id' => $recipient->id,
            'category' => 'admin_dm',
            'priority' => $isMentioned ? 'high' : 'normal',
            'title' => $isMentioned ? 'You were mentioned in a direct message' : 'New direct message',
            'body' => "{$sender->name}: {$preview}",
            'action_label' => 'Open messages',
            'action_url' => $openUrl,
            'data' => [
                'dedupe_key' => "admin_dm:{$conversation->id}:{$message->id}:{$recipient->id}",
                'message_id' => $message->id,
                'conversation_id' => $conversation->id,
                'sender_id' => $sender->id,
                'open_messenger' => true,
            ],
        ]);
    }

    /**
     * @param  array<int, mixed>|null  $attachments
     */
    private function attachmentPreview(?array $attachments): string
    {
        if (! is_array($attachments) || $attachments === []) {
            return '[Attachment]';
        }

        foreach ($attachments as $att) {
            if (is_array($att) && (($att['type'] ?? '') === 'gif' || str_contains((string) ($att['mime'] ?? ''), 'gif'))) {
                return '[GIF]';
            }
        }

        return '[Attachment]';
    }

    public function notifyTeamChatMessage(User $sender, StaffTeamChatMessage $message, StaffTeamChatRoom $room): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $preview = Str::limit(trim((string) $message->body) ?: '[Attachment]', 140);
        $mentionedIds = collect($message->mentions ?? [])->map(fn ($id) => (int) $id)->filter()->all();

        User::query()
            ->with('role:id,slug')
            ->where('id', '!=', $sender->id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->get(['id', 'name', 'role_id'])
            ->each(function (User $recipient) use ($sender, $message, $room, $preview, $mentionedIds): void {
                if (! $this->recipientWantsTeamChatInApp($recipient)) {
                    return;
                }

                if (MessagingViewPresence::isViewing(
                    MessagingViewPresence::SCOPE_TEAM_CHAT_ROOM,
                    (int) $room->id,
                    (int) $recipient->id,
                )) {
                    return;
                }

                $isMentioned = in_array((int) $recipient->id, $mentionedIds, true);
                $chatUrl = $recipient->role?->slug === 'super_admin'
                    ? route('admin.team-chat.index')
                    : route('operations.team-chat.index');

                AdminNotification::query()->create([
                    'admin_user_id' => $recipient->id,
                    'category' => 'team_chat',
                    'priority' => $isMentioned ? 'high' : 'normal',
                    'title' => $isMentioned ? 'You were mentioned in team chat' : 'New team chat message',
                    'body' => "{$sender->name}: {$preview}",
                    'action_label' => 'Open team chat',
                    'action_url' => $chatUrl,
                    'data' => [
                        'dedupe_key' => "team_chat:{$room->id}:{$message->id}:{$recipient->id}",
                        'message_id' => $message->id,
                        'room_id' => $room->id,
                        'sender_id' => $sender->id,
                    ],
                ]);
            });
    }

    private function recipientWantsTeamChatInApp(User $recipient): bool
    {
        $defaults = config('operations.notification_events.team_chat_message', []);
        $stored = StaffNotificationPreference::query()
            ->where('staff_user_id', $recipient->id)
            ->value('preferences');

        return data_get($stored, 'team_chat_message.in_app', $defaults['default_in_app'] ?? true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function criticalBanners(User $staff): array
    {
        if (! Schema::hasTable('admin_notifications')) {
            return [];
        }

        return AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->where('priority', 'critical')
            ->whereNull('actioned_at')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (AdminNotification $n) => $this->payload($n, $staff))
            ->all();
    }

    public function resolvedActionUrl(AdminNotification $notification, User $viewer): string
    {
        $data = $notification->data ?? [];
        $stored = (string) ($notification->action_url ?? '');

        if ($viewer->role?->slug === 'super_admin') {
            if ($notification->category === 'customer_support' && ! empty($data['ticket_id'])) {
                return route('admin.customer-support.index', ['ticket' => $data['ticket_id']]);
            }
            if ($notification->category === 'team_chat') {
                return route('admin.team-chat.index');
            }
            if ($notification->category === 'admin_dm' || ! empty($data['open_messenger'])) {
                return route('admin.dashboard', array_filter([
                    'open_messenger' => 1,
                    'conversation' => $data['conversation_id'] ?? null,
                ]));
            }
            if (in_array($notification->category, ['dispute', 'disputes'], true)) {
                $uuid = (string) ($data['dispute_uuid'] ?? '');
                if ($uuid !== '') {
                    return route('admin.disputes.index', ['q' => $uuid]);
                }
            }
            if ($notification->category === 'dispute' && ! empty($data['dispute_id'])) {
                return route('admin.disputes.index', ['dispute' => $data['dispute_id']]);
            }
            if ($notification->category === 'task' || $notification->category === 'assignment') {
                return route('admin.tasks.index');
            }
            if ($notification->category === 'kyc' && ! empty($data['verification_id'])) {
                return route('admin.verification-engine.index');
            }
            if ($notification->category === 'sla') {
                return $stored !== '' ? $stored : route('admin.alerts.index');
            }

            return $stored !== '' ? $stored : route('admin.alerts.index');
        }

        if ($notification->category === 'customer_support' && ! empty($data['ticket_id'])) {
            return route('operations.customer-support.index', ['ticket' => $data['ticket_id']]);
        }

        if ($notification->category === 'team_chat') {
            return route('operations.team-chat.index');
        }

        if ($notification->category === 'admin_dm' || ! empty($data['open_messenger'])) {
            return route('operations.dashboard', array_filter([
                'open_messenger' => 1,
                'conversation' => $data['conversation_id'] ?? null,
            ]));
        }

        if (in_array($notification->category, ['dispute', 'disputes'], true)) {
            $uuid = (string) ($data['dispute_uuid'] ?? '');
            if ($uuid !== '') {
                return route('operations.disputes.index', ['q' => $uuid]);
            }
            if (! empty($data['dispute_id'])) {
                return route('operations.disputes.index', ['q' => (string) $data['dispute_id']]);
            }
        }

        if ($notification->category === 'dispute' && ! empty($data['dispute_id'])) {
            return route('operations.disputes.index', ['dispute' => $data['dispute_id']]);
        }

        if ($notification->category === 'task' || $notification->category === 'assignment') {
            return route('operations.tasks.index');
        }

        if ($notification->category === 'kyc' && ! empty($data['verification_id'])) {
            return route('operations.verifications.index');
        }

        if ($notification->category === 'support') {
            return route('operations.support.index');
        }

        if ($notification->category === 'hr') {
            return $stored !== '' ? $stored : route('operations.account.index');
        }

        if ($stored !== '' && str_contains($stored, '/admin/')) {
            return route('operations.dashboard');
        }

        return $stored !== '' ? $stored : route('operations.notifications.index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyHrImpact(
        User $staff,
        string $dedupeKey,
        string $title,
        string $body,
        string $actionLabel,
        string $actionUrl,
        string $priority = 'normal',
        array $data = [],
    ): void {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        if ($staff->role?->slug !== 'admin') {
            return;
        }

        $this->upsert($staff, $dedupeKey, [
            'category' => 'hr',
            'priority' => $priority,
            'title' => $title,
            'body' => $body,
            'action_label' => $actionLabel,
            'action_url' => $actionUrl,
            'data' => $data,
        ]);
    }

    private function upsert(User $staff, string $dedupeKey, array $attrs): void
    {
        $data = array_merge($attrs['data'] ?? [], ['dedupe_key' => $dedupeKey]);
        $existing = AdminNotification::query()
            ->where('admin_user_id', $staff->id)
            ->where('data->dedupe_key', $dedupeKey)
            ->first();

        if ($existing) {
            $fill = [...$attrs, 'data' => $data];
            if (array_key_exists('read_at', $attrs)) {
                $fill['read_at'] = $attrs['read_at'];
            }
            $existing->forceFill($fill)->save();

            return;
        }

        AdminNotification::query()->create([
            'admin_user_id' => $staff->id,
            ...$attrs,
            'data' => $data,
        ]);
    }

    private function syncTasks(User $staff): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            return;
        }

        AdminTask::query()
            ->where('assigned_to_admin_id', $staff->id)
            ->where('status', '<>', 'done')
            ->latest()
            ->limit(30)
            ->get()
            ->each(function (AdminTask $task) use ($staff): void {
                $overdue = $task->due_at && $task->due_at->isPast();
                $this->upsert($staff, "task:{$task->id}", [
                    'category' => $overdue ? 'task' : 'assignment',
                    'priority' => $overdue ? 'critical' : 'high',
                    'title' => $overdue ? 'Overdue task' : 'Task assigned',
                    'body' => $task->title,
                    'action_label' => 'Open task',
                    'action_url' => route('operations.tasks.index'),
                    'data' => ['task_id' => $task->id, 'dedupe_key' => "task:{$task->id}"],
                ]);
            });
    }

    private function syncDisputes(User $staff): void
    {
        if (! Schema::hasTable('quest_disputes')) {
            return;
        }

        QuestDispute::query()
            ->with('quest:id,title,reference_code')
            ->where('assigned_staff_id', $staff->id)
            ->whereNotIn('status', ['resolved', 'closed_withdrawn'])
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (QuestDispute $dispute) use ($staff): void {
                $this->upsert($staff, "dispute:{$dispute->id}", [
                    'category' => 'dispute',
                    'priority' => $dispute->assigned_staff_id ? 'high' : 'critical',
                    'title' => 'Dispute needs attention',
                    'body' => $dispute->quest?->title ?? "Dispute #{$dispute->id}",
                    'action_label' => 'Open dispute',
                    'action_url' => route('operations.disputes.index'),
                    'data' => ['dispute_id' => $dispute->id, 'dedupe_key' => "dispute:{$dispute->id}"],
                ]);
            });
    }

    private function syncKyc(User $staff): void
    {
        UserVerification::query()
            ->with('user:id,name,email')
            ->whereIn('status', ['pending', 'in_review', 'flagged'])
            ->oldest('submitted_at')
            ->limit(15)
            ->get()
            ->each(function (UserVerification $v) use ($staff): void {
                $this->upsert($staff, "kyc:{$v->id}", [
                    'category' => 'kyc',
                    'priority' => 'medium',
                    'title' => 'KYC ready for review',
                    'body' => ($v->user?->name ?? 'User').' · '.($v->verification_type ?: 'verification'),
                    'action_label' => 'Review',
                    'action_url' => route('operations.verifications.index'),
                    'data' => ['verification_id' => $v->id, 'dedupe_key' => "kyc:{$v->id}"],
                ]);
            });
    }

    private function syncChats(User $staff): void
    {
        if (! Schema::hasTable('support_chat_assignments')) {
            return;
        }

        SupportChatAssignment::query()
            ->with('thread.quest:id,title')
            ->where('assigned_admin_id', $staff->id)
            ->where('status', 'open')
            ->latest('assigned_at')
            ->limit(15)
            ->get()
            ->each(function (SupportChatAssignment $chat) use ($staff): void {
                $this->upsert($staff, "chat:{$chat->id}", [
                    'category' => 'support',
                    'priority' => 'medium',
                    'title' => 'CS chat waiting',
                    'body' => $chat->thread?->quest?->title ?? 'Support conversation',
                    'action_label' => 'Open inbox',
                    'action_url' => route('operations.support.index'),
                    'data' => ['chat_assignment_id' => $chat->id, 'dedupe_key' => "chat:{$chat->id}"],
                ]);
            });
    }

    public function notifyManagedSupportTicketUpdated(
        User $creator,
        SupportTicket $ticket,
        User $updater,
        string $summary,
    ): void {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        if ((int) $creator->id === (int) $updater->id) {
            return;
        }

        $url = $creator->role?->slug === 'super_admin'
            ? route('admin.support-tickets.show', $ticket->uuid)
            : route('operations.support-tickets.show', $ticket->uuid);

        AdminNotification::query()->create([
            'admin_user_id' => $creator->id,
            'category' => 'customer_support',
            'priority' => in_array($ticket->priority, ['urgent', 'critical', 'high'], true) ? 'high' : 'normal',
            'title' => 'Support ticket updated',
            'body' => Str::limit("{$updater->name}: {$summary} · {$ticket->ticket_reference}", 240),
            'action_label' => 'View ticket',
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "managed_ticket_update:{$ticket->id}:{$creator->id}:".now()->timestamp,
                'ticket_id' => $ticket->id,
                'updated_by_user_id' => $updater->id,
            ],
        ]);
    }

    public function notifyCustomerSupportAssigned(User $admin, SupportTicket $ticket): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $url = $admin->role?->slug === 'super_admin'
            ? route('admin.support-tickets.index')
            : route('operations.support.index');

        AdminNotification::query()->create([
            'admin_user_id' => $admin->id,
            'category' => 'customer_support',
            'priority' => in_array($ticket->priority, ['urgent', 'high'], true) ? 'high' : 'normal',
            'title' => 'Customer support chat assigned',
            'body' => $ticket->subject,
            'action_label' => 'Open chat',
            'action_url' => $url,
            'data' => [
                'dedupe_key' => "cs_assign:{$ticket->id}:{$admin->id}",
                'ticket_id' => $ticket->id,
            ],
        ]);
    }

    public function notifyCustomerSupportMessage(User $recipient, SupportTicket $ticket, User $sender): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        if (MessagingViewPresence::isViewing(
            MessagingViewPresence::SCOPE_CUSTOMER_SUPPORT,
            (int) $ticket->id,
            (int) $recipient->id,
        )) {
            return;
        }

        $isAdminRecipient = in_array($recipient->role?->slug, ['admin', 'super_admin'], true);

        if ($isAdminRecipient) {
            AdminNotification::query()->create([
                'admin_user_id' => $recipient->id,
                'category' => 'customer_support',
                'priority' => 'normal',
                'title' => 'New customer message',
                'body' => "{$sender->name}: ".Str::limit($ticket->subject, 80),
                'action_label' => 'Reply',
                'action_url' => $recipient->role?->slug === 'super_admin'
                    ? route('admin.customer-support.index', ['ticket' => $ticket->uuid])
                    : route('operations.customer-support.index', ['ticket' => $ticket->uuid]),
                'data' => [
                    'dedupe_key' => "cs_msg:{$ticket->id}:{$recipient->id}:".now()->format('YmdHi'),
                    'ticket_id' => $ticket->id,
                ],
            ]);

            return;
        }

        $recipient->notify(new \App\Notifications\AdminUserMessageNotification(
            'Support replied',
            'Your support conversation has a new reply.',
        ));
    }

    public function notifyCustomerSupportQueued(SupportTicket $ticket): void
    {
        if (! Schema::hasTable('admin_notifications')) {
            return;
        }

        $adminIds = $this->onlineAdminIds();
        if ($adminIds->isEmpty()) {
            return;
        }

        User::query()
            ->whereIn('id', $adminIds)
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->get(['id'])
            ->each(function (User $admin) use ($ticket): void {
                AdminNotification::query()->create([
                    'admin_user_id' => $admin->id,
                    'category' => 'customer_support',
                    'priority' => in_array($ticket->priority, ['urgent', 'high'], true) ? 'high' : 'normal',
                    'title' => 'New live support chat waiting',
                    'body' => $ticket->subject,
                    'action_label' => 'Open queue',
                    'action_url' => route('operations.customer-support.index', ['ticket' => $ticket->uuid]),
                    'data' => [
                        'dedupe_key' => "cs_queue:{$ticket->id}:{$admin->id}",
                        'ticket_id' => $ticket->id,
                    ],
                ]);
            });
    }

    /**
     * @return \Illuminate\Support\Collection<int, int>
     */
    private function onlineAdminIds(): \Illuminate\Support\Collection
    {
        $window = (int) config('customer_support.online_window_minutes', 5);

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->where('last_active_at', '>=', now()->subMinutes($window))
            ->orderBy('id')
            ->pluck('id');
    }

    private function payload(AdminNotification $notification, ?User $viewer = null): array
    {
        $actionUrl = $viewer
            ? $this->resolvedActionUrl($notification, $viewer)
            : $notification->action_url;

        return [
            'id' => $notification->id,
            'category' => $notification->category,
            'priority' => $notification->priority,
            'title' => $notification->title,
            'body' => $notification->body,
            'action_label' => $notification->action_label,
            'action_url' => $actionUrl,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->toIso8601String(),
            'actioned_at' => $notification->actioned_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'is_critical_open' => $notification->priority === 'critical' && $notification->actioned_at === null,
        ];
    }
}
