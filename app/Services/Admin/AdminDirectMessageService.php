<?php

namespace App\Services\Admin;

use App\Events\AdminDirectMessageSent;
use App\Events\AdminDirectTyping;
use App\Models\AdminDirectConversation;
use App\Models\AdminDirectMessage;
use App\Models\AdminDirectMessageReceipt;
use App\Models\User;
use App\Services\Operations\StaffNotificationCentreService;
use App\Support\MessagingViewPresence;
use App\Support\ChatAttachmentHelper;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdminDirectMessageService
{
    public function __construct(private readonly StaffNotificationCentreService $notificationCentre) {}
    /**
     * @return list<array<string, mixed>>
     */
    public function staffDirectory(User $viewer): array
    {
        return User::query()
            ->with('role:id,slug')
            ->where('id', '!=', $viewer->id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'first_name', 'username', 'email', 'avatar_url', 'role_id'])
            ->map(fn (User $user) => $this->staffPayload($user))
            ->values()
            ->all();
    }

    public function unreadCount(User $viewer): int
    {
        return AdminDirectMessageReceipt::query()
            ->where('user_id', $viewer->id)
            ->whereNull('read_at')
            ->whereHas('message', fn ($q) => $q->where('user_id', '!=', $viewer->id))
            ->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conversations(User $viewer, ?string $search = null): array
    {
        $query = AdminDirectConversation::query()
            ->with([
                'userOne:id,name,first_name,username,email,avatar_url,role_id',
                'userOne.role:id,slug',
                'userTwo:id,name,first_name,username,email,avatar_url,role_id',
                'userTwo.role:id,slug',
                'lastMessage.sender:id,name',
            ])
            ->where(fn ($q) => $q->where('user_one_id', $viewer->id)->orWhere('user_two_id', $viewer->id))
            ->orderByDesc('last_message_at')
            ->orderByDesc('id');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.trim($search).'%';
            $staffIds = User::query()
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
                ->where(function ($q) use ($term): void {
                    $q->where('name', 'like', $term)
                        ->orWhere('username', 'like', $term)
                        ->orWhere('email', 'like', $term);
                })
                ->pluck('id');

            $query->where(function ($q) use ($viewer, $staffIds): void {
                $q->where(function ($inner) use ($viewer, $staffIds): void {
                    $inner->where('user_one_id', $viewer->id)->whereIn('user_two_id', $staffIds);
                })->orWhere(function ($inner) use ($viewer, $staffIds): void {
                    $inner->where('user_two_id', $viewer->id)->whereIn('user_one_id', $staffIds);
                });
            });
        }

        return $query->limit(100)->get()
            ->map(fn (AdminDirectConversation $c) => $this->conversationPayload($c, $viewer))
            ->all();
    }

    public function findOrCreateConversation(User $viewer, int $otherUserId): AdminDirectConversation
    {
        $other = User::query()
            ->whereKey($otherUserId)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->firstOrFail();

        [$one, $two] = AdminDirectConversation::canonicalPair((int) $viewer->id, (int) $other->id);

        return AdminDirectConversation::query()->firstOrCreate(
            ['user_one_id' => $one, 'user_two_id' => $two],
            ['last_message_at' => null],
        );
    }

    /**
     * @return array{items: list<array<string, mixed>>, has_more: bool}
     */
    public function messages(AdminDirectConversation $conversation, User $viewer, ?int $beforeId = null, int $limit = 40): array
    {
        abort_unless($conversation->includesUser((int) $viewer->id), 403);

        $query = AdminDirectMessage::query()
            ->with(['sender:id,name,username,avatar_url,role_id', 'sender.role:id,slug', 'receipts'])
            ->where('admin_direct_conversation_id', $conversation->id)
            ->orderByDesc('id');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit)->get()->reverse()->values();

        return [
            'items' => $rows->map(fn (AdminDirectMessage $m) => $this->messagePayload($m, $viewer))->all(),
            'has_more' => $rows->count() >= $limit,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messagesSince(AdminDirectConversation $conversation, User $viewer, int $afterId): array
    {
        abort_unless($conversation->includesUser((int) $viewer->id), 403);

        return AdminDirectMessage::query()
            ->with(['sender:id,name,username,avatar_url,role_id', 'sender.role:id,slug', 'receipts'])
            ->where('admin_direct_conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (AdminDirectMessage $m) => $this->messagePayload($m, $viewer))
            ->all();
    }

    public function send(AdminDirectConversation $conversation, User $sender, array $data, ?array $files = null): array
    {
        abort_unless($conversation->includesUser((int) $sender->id), 403);

        $attachments = ChatAttachmentHelper::storeUploadedFiles($files, 'admin-direct-messages');
        $gifUrl = trim((string) ($data['gif_url'] ?? ''));
        if ($gifUrl !== '') {
            $attachments[] = ChatAttachmentHelper::remoteGif($gifUrl);
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && $attachments === []) {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        $recipientId = $conversation->user_one_id === $sender->id
            ? (int) $conversation->user_two_id
            : (int) $conversation->user_one_id;

        $message = AdminDirectMessage::query()->create([
            'admin_direct_conversation_id' => $conversation->id,
            'user_id' => $sender->id,
            'body' => $body,
            'attachments' => $attachments,
            'mentions' => $this->parseMentions($body),
        ]);

        AdminDirectMessageReceipt::query()->create([
            'admin_direct_message_id' => $message->id,
            'user_id' => $recipientId,
        ]);

        $conversation->forceFill([
            'last_message_id' => $message->id,
            'last_message_at' => $message->created_at,
        ])->save();

        $message->load(['sender.role', 'receipts']);
        $payload = $this->messagePayload($message, $sender);

        $this->broadcastAfterResponse(new AdminDirectMessageSent($conversation->id, $payload));

        try {
            $this->notificationCentre->notifyDirectMessage($sender, $message, $conversation, $recipientId);
        } catch (Throwable $e) {
            Log::warning('admin_dm.notify_failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payload;
    }

    public function markDelivered(AdminDirectMessage $message, User $recipient): void
    {
        if ((int) $message->user_id === (int) $recipient->id) {
            return;
        }

        AdminDirectMessageReceipt::query()
            ->where('admin_direct_message_id', $message->id)
            ->where('user_id', $recipient->id)
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);
    }

    public function markRead(AdminDirectConversation $conversation, User $viewer, ?int $upToMessageId = null): void
    {
        abort_unless($conversation->includesUser((int) $viewer->id), 403);

        $query = AdminDirectMessage::query()
            ->where('admin_direct_conversation_id', $conversation->id)
            ->where('user_id', '!=', $viewer->id);

        if ($upToMessageId !== null) {
            $query->where('id', '<=', $upToMessageId);
        }

        $messageIds = $query->pluck('id');
        if ($messageIds->isEmpty()) {
            return;
        }

        $now = now();
        AdminDirectMessageReceipt::query()
            ->whereIn('admin_direct_message_id', $messageIds)
            ->where('user_id', $viewer->id)
            ->update([
                'delivered_at' => $now,
                'read_at' => $now,
            ]);

        MessagingViewPresence::touch(
            MessagingViewPresence::SCOPE_ADMIN_DM,
            (int) $conversation->id,
            (int) $viewer->id,
        );
    }

    public function typing(AdminDirectConversation $conversation, User $user, bool $typing): void
    {
        abort_unless($conversation->includesUser((int) $user->id), 403);

        if ($typing) {
            MessagingViewPresence::touch(
                MessagingViewPresence::SCOPE_ADMIN_DM,
                (int) $conversation->id,
                (int) $user->id,
            );
        }

        $this->safeBroadcast(new AdminDirectTyping(
            $conversation->id,
            $user->id,
            (string) $user->name,
            $typing,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function conversationPayload(AdminDirectConversation $conversation, User $viewer): array
    {
        $other = $conversation->otherParticipant((int) $viewer->id);
        $preview = $conversation->lastMessage;

        $unread = AdminDirectMessageReceipt::query()
            ->where('user_id', $viewer->id)
            ->whereNull('read_at')
            ->whereHas('message', fn ($q) => $q
                ->where('admin_direct_conversation_id', $conversation->id)
                ->where('user_id', '!=', $viewer->id))
            ->count();

        return [
            'id' => $conversation->id,
            'participant' => $other ? $this->staffPayload($other) : null,
            'last_message' => $preview ? [
                'id' => $preview->id,
                'body' => $preview->body,
                'sender_id' => $preview->user_id,
                'created_at' => $preview->created_at?->toIso8601String(),
            ] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread_count' => $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function messagePayload(AdminDirectMessage $message, User $viewer): array
    {
        $recipientReceipt = $message->receipts->firstWhere('user_id', '!=', $message->user_id);
        $viewerReceipt = $message->receipts->firstWhere('user_id', $viewer->id);

        $isMine = (int) $message->user_id === (int) $viewer->id;

        return [
            'id' => $message->id,
            'conversation_id' => $message->admin_direct_conversation_id,
            'body' => $message->body,
            'attachments' => ChatAttachmentHelper::normalizeList($message->attachments),
            'mentions' => $message->mentions ?? [],
            'sender' => $this->staffPayload($message->sender),
            'mine' => $isMine,
            'status' => $isMine ? $this->outgoingStatus($recipientReceipt) : null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    private function outgoingStatus(?AdminDirectMessageReceipt $receipt): string
    {
        if ($receipt?->read_at) {
            return 'read';
        }
        if ($receipt?->delivered_at) {
            return 'delivered';
        }

        return 'sent';
    }

    /**
     * @return array<string, mixed>
     */
    private function staffPayload(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $this->mentionHandleFor($user),
            'email' => $user->email,
            'avatar_url' => $this->resolveAvatarUrl($user),
            'role' => $user->role?->slug === 'super_admin' ? 'Super Admin' : 'Staff',
            'role_slug' => $user->role?->slug,
        ];
    }

    private function mentionHandleFor(User $user): string
    {
        $username = trim((string) ($user->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '' && str_contains($email, '@')) {
            return Str::before($email, '@');
        }

        return Str::slug((string) ($user->name ?? ''), '');
    }

    /**
     * @return list<int>
     */
    private function parseMentions(string $body): array
    {
        preg_match_all('/@([a-zA-Z0-9_.-]+)/', $body, $matches);
        if (empty($matches[1])) {
            return [];
        }

        $usernames = collect($matches[1])->map(fn (string $u) => strtolower($u))->unique()->values()->all();

        return User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->get(['id', 'username', 'email', 'name'])
            ->filter(fn (User $user) => in_array(strtolower($this->mentionHandleFor($user)), $usernames, true))
            ->pluck('id')
            ->all();
    }

    private function resolveAvatarUrl(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $url = trim((string) ($user->avatar_url ?? ''));
        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://') || str_starts_with($url, '//')) {
            return $url;
        }

        return url(str_starts_with($url, '/') ? $url : '/'.$url);
    }

    private function broadcastAfterResponse(object $event): void
    {
        dispatch(static function () use ($event): void {
            if (config('broadcasting.default') === 'null') {
                return;
            }

            try {
                broadcast($event);
            } catch (Throwable $e) {
                Log::warning('admin_dm.broadcast_failed', [
                    'event' => $event::class,
                    'error' => $e->getMessage(),
                ]);
            }
        })->afterResponse();
    }

    private function safeBroadcast(object $event): void
    {
        if (config('broadcasting.default') === 'null') {
            return;
        }

        try {
            broadcast($event);
        } catch (Throwable $e) {
            Log::warning('admin_dm.broadcast_failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
