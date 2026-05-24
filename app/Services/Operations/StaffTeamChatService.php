<?php

namespace App\Services\Operations;

use App\Events\StaffTeamChatMessageSent;
use App\Events\StaffTeamChatTyping;
use App\Models\StaffTeamChatMessage;
use App\Models\StaffTeamChatPin;
use App\Models\StaffTeamChatReaction;
use App\Models\StaffTeamChatRead;
use App\Models\StaffTeamChatRoom;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Support\ChatAttachmentHelper;
use App\Support\MessagingViewPresence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StaffTeamChatService
{
    public function __construct(
        private readonly AdminActivityLogger $logger,
        private readonly StaffNotificationCentreService $notificationCentre,
    ) {}

    public function globalRoom(): StaffTeamChatRoom
    {
        return StaffTeamChatRoom::query()->firstOrCreate(
            ['slug' => 'global'],
            ['name' => 'Operations team', 'type' => 'global'],
        );
    }

    public function rooms(): array
    {
        return StaffTeamChatRoom::query()->orderBy('name')->get()->map(fn (StaffTeamChatRoom $room) => [
            'id' => $room->id,
            'slug' => $room->slug,
            'name' => $room->name,
            'type' => $room->type,
        ])->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function bootstrapPayload(StaffTeamChatRoom $room, User $viewer): array
    {
        $messages = $this->messages($room, $viewer);

        return [
            'rooms' => $this->rooms(),
            'room' => ['id' => $room->id, 'name' => $room->name],
            'messages' => $messages['items'],
            'has_more' => $messages['has_more'],
            'pinned' => $this->pinned($room),
            'presence' => $this->presence(),
            'mentionables' => $this->mentionableUsers($viewer),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function mentionableUsers(User $viewer): array
    {
        return User::query()
            ->with('role:id,slug')
            ->where('id', '!=', $viewer->id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'avatar_url', 'role_id'])
            ->map(function (User $user) {
                $handle = $this->mentionHandleFor($user);

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $handle,
                    'avatar_url' => $this->resolveAvatarUrl($user),
                    'role' => $user->role?->slug === 'super_admin' ? 'Super Admin' : 'Staff',
                ];
            })
            ->filter(fn (array $row) => $row['username'] !== '')
            ->values()
            ->all();
    }

    public function messages(StaffTeamChatRoom $room, User $viewer, ?int $beforeId = null, int $limit = 40): array
    {
        $query = StaffTeamChatMessage::query()
            ->with(['user:id,name,email,avatar_url,role_id', 'user.role:id,slug', 'reactions.user:id,name'])
            ->withCount('reads')
            ->where('staff_team_chat_room_id', $room->id)
            ->whereNull('removed_at')
            ->orderByDesc('id');

        if ($beforeId) {
            $query->where('id', '<', $beforeId);
        }

        $rows = $query->limit($limit)->get()->reverse()->values();

        return [
            'items' => $rows->map(fn (StaffTeamChatMessage $m) => $this->messagePayload($m, $viewer))->all(),
            'has_more' => $rows->count() >= $limit,
        ];
    }

    public function messagesSince(StaffTeamChatRoom $room, User $viewer, int $afterId): array
    {
        return StaffTeamChatMessage::query()
            ->with(['user:id,name,email,avatar_url,role_id', 'user.role:id,slug', 'reactions.user:id,name'])
            ->withCount('reads')
            ->where('staff_team_chat_room_id', $room->id)
            ->whereNull('removed_at')
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (StaffTeamChatMessage $m) => $this->messagePayload($m, $viewer))
            ->all();
    }

    public function send(StaffTeamChatRoom $room, User $sender, array $data, ?array $files = null): array
    {
        $attachments = ChatAttachmentHelper::storeUploadedFiles($files, 'staff-team-chat');
        $gifUrl = trim((string) ($data['gif_url'] ?? ''));
        if ($gifUrl !== '') {
            $attachments[] = ChatAttachmentHelper::remoteGif($gifUrl);
        }
        $body = trim((string) ($data['body'] ?? ''));
        if ($body === '' && $attachments === []) {
            throw ValidationException::withMessages(['body' => 'Message cannot be empty.']);
        }

        $isOfficial = $sender->role?->slug === 'super_admin' && (bool) ($data['is_official_guidance'] ?? false);

        $message = StaffTeamChatMessage::query()->create([
            'staff_team_chat_room_id' => $room->id,
            'user_id' => $sender->id,
            'body' => $body,
            'attachments' => $attachments,
            'mentions' => $this->parseMentions($body),
            'is_official_guidance' => $isOfficial,
        ]);

        $message->loadCount('reads');
        $message->load(['user.role', 'reactions.user']);
        $payload = $this->messagePayload($message, $sender);

        $this->broadcastMessageAfterResponse($room->id, $payload);

        try {
            $this->notificationCentre->notifyTeamChatMessage($sender, $message, $room);
        } catch (Throwable $e) {
            Log::warning('staff_team_chat.notify_failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->logger->log($sender, 'staff_team_chat.message_sent', StaffTeamChatMessage::class, $message->id, [
                'room_id' => $room->id,
            ]);
        } catch (Throwable $e) {
            Log::warning('staff_team_chat.activity_log_failed', [
                'message_id' => $message->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $payload;
    }

    public function react(StaffTeamChatMessage $message, User $user, string $emoji): array
    {
        $allowed = ['👍', '❤️', '⚠️', '😂', '🎉'];
        if (! in_array($emoji, $allowed, true)) {
            throw ValidationException::withMessages(['emoji' => 'Reaction not allowed.']);
        }

        $existing = StaffTeamChatReaction::query()
            ->where('staff_team_chat_message_id', $message->id)
            ->where('user_id', $user->id)
            ->where('emoji', $emoji)
            ->first();

        if ($existing) {
            $existing->delete();
        } else {
            StaffTeamChatReaction::query()->create([
                'staff_team_chat_message_id' => $message->id,
                'user_id' => $user->id,
                'emoji' => $emoji,
            ]);
        }

        $message->loadCount('reads');
        $message->load(['user.role', 'reactions.user']);
        $payload = $this->messagePayload($message, $user);
        $this->broadcastMessageAfterResponse($message->staff_team_chat_room_id, $payload);

        return $payload;
    }

    public function pin(StaffTeamChatRoom $room, StaffTeamChatMessage $message, User $admin): void
    {
        StaffTeamChatPin::query()->firstOrCreate([
            'staff_team_chat_room_id' => $room->id,
            'staff_team_chat_message_id' => $message->id,
        ], ['pinned_by_admin_id' => $admin->id]);
    }

    public function pinned(StaffTeamChatRoom $room): array
    {
        return StaffTeamChatPin::query()
            ->with([
                'message' => fn ($q) => $q
                    ->withCount('reads')
                    ->whereNull('removed_at')
                    ->with(['user:id,name,email,avatar_url,role_id', 'user.role:id,slug', 'reactions.user:id,name']),
            ])
            ->where('staff_team_chat_room_id', $room->id)
            ->latest()
            ->limit(10)
            ->get()
            ->filter(fn (StaffTeamChatPin $pin) => $pin->message !== null)
            ->map(fn (StaffTeamChatPin $pin) => $this->messagePayload($pin->message, $pin->message->user))
            ->values()
            ->all();
    }

    public function markRead(StaffTeamChatRoom $room, User $user, ?int $upToMessageId = null): void
    {
        if ($upToMessageId !== null) {
            $readAt = now();
            $unreadIds = StaffTeamChatMessage::query()
                ->where('staff_team_chat_room_id', $room->id)
                ->where('id', '<=', $upToMessageId)
                ->where('user_id', '!=', $user->id)
                ->whereDoesntHave('reads', fn ($q) => $q->where('user_id', $user->id))
                ->pluck('id');

            if ($unreadIds->isNotEmpty()) {
                $rows = $unreadIds->map(fn (int $messageId) => [
                    'staff_team_chat_message_id' => $messageId,
                    'user_id' => $user->id,
                    'read_at' => $readAt,
                ])->all();

                StaffTeamChatRead::query()->insertOrIgnore($rows);
            }
        }

        $this->notificationCentre->markTeamChatNotificationsRead($user);

        MessagingViewPresence::touch(
            MessagingViewPresence::SCOPE_TEAM_CHAT_ROOM,
            (int) $room->id,
            (int) $user->id,
        );
    }

    public function typing(StaffTeamChatRoom $room, User $user, bool $typing): void
    {
        if ($typing) {
            MessagingViewPresence::touch(
                MessagingViewPresence::SCOPE_TEAM_CHAT_ROOM,
                (int) $room->id,
                (int) $user->id,
            );
        }

        $this->safeBroadcast(new StaffTeamChatTyping($room->id, $user->id, $user->name, $typing));
        $this->touchPresence($user);
    }

    public function presence(): array
    {
        $admins = User::query()
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'super_admin']))
            ->get(['id', 'name', 'email', 'avatar_url']);

        return $admins->map(function (User $user) {
            $lastSeen = $this->resolvePresenceTimestamp(Cache::get($this->presenceKey($user->id)));

            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $this->resolveAvatarUrl($user),
                'online' => $lastSeen !== null && $lastSeen->diffInSeconds(now()) < 90,
                'last_seen_at' => $lastSeen?->toIso8601String(),
            ];
        })->sortByDesc('online')->values()->all();
    }

    public function touchPresence(User $user): void
    {
        Cache::put($this->presenceKey($user->id), now()->toIso8601String(), now()->addMinutes(5));
    }

    public function search(StaffTeamChatRoom $room, string $q): array
    {
        return StaffTeamChatMessage::query()
            ->with('user:id,name')
            ->where('staff_team_chat_room_id', $room->id)
            ->whereNull('removed_at')
            ->where('body', 'like', '%'.$q.'%')
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (StaffTeamChatMessage $m) => [
                'id' => $m->id,
                'body' => $m->body,
                'sender' => $m->user?->name,
                'created_at' => $m->created_at?->toIso8601String(),
            ])
            ->all();
    }

    public function removeMessage(StaffTeamChatMessage $message, User $superAdmin): void
    {
        if ($superAdmin->role?->slug !== 'super_admin') {
            throw ValidationException::withMessages(['message' => 'Only Super Admins can remove messages.']);
        }

        $message->forceFill([
            'removed_at' => now(),
            'removed_by_admin_id' => $superAdmin->id,
        ])->save();

        $this->logger->log($superAdmin, 'staff_team_chat.message_removed', StaffTeamChatMessage::class, $message->id, []);
    }

    private function messagePayload(StaffTeamChatMessage $message, User $viewer): array
    {
        $readCount = (int) ($message->reads_count ?? $message->reads()->count());

        return [
            'id' => $message->id,
            'room_id' => $message->staff_team_chat_room_id,
            'body' => $message->body,
            'attachments' => ChatAttachmentHelper::normalizeList($message->attachments),
            'mentions' => $message->mentions ?? [],
            'is_official_guidance' => $message->is_official_guidance,
            'sender' => [
                'id' => $message->user?->id,
                'name' => $message->user?->name,
                'email' => $message->user?->email,
                'avatar_url' => $this->resolveAvatarUrl($message->user),
                'role' => $message->user?->role?->slug === 'super_admin' ? 'Super Admin' : 'Staff',
                'role_slug' => $message->user?->role?->slug,
            ],
            'reactions' => $message->reactions->groupBy('emoji')->map(fn ($group, $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'users' => $group->pluck('user.name')->filter()->values(),
                'mine' => $group->contains(fn ($r) => (int) $r->user_id === (int) $viewer->id),
            ])->values(),
            'read_count' => $readCount,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
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
            ->filter(function (User $user) use ($usernames): bool {
                $handle = strtolower($this->mentionHandleFor($user));

                return $handle !== '' && in_array($handle, $usernames, true);
            })
            ->pluck('id')
            ->all();
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

    private function presenceKey(int $userId): string
    {
        return 'staff_team_presence:'.$userId;
    }

    private function resolvePresenceTimestamp(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value);
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            try {
                return Carbon::parse($value);
            } catch (Throwable) {
                return null;
            }
        }

        return null;
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

        if (str_starts_with($url, '/')) {
            return url($url);
        }

        if (str_starts_with($url, 'storage/')) {
            return url('/'.$url);
        }

        if (Str::contains($url, '/')) {
            return Storage::disk('public')->url($url);
        }

        return url('/'.$url);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function broadcastMessageAfterResponse(int $roomId, array $payload): void
    {
        $event = new StaffTeamChatMessageSent($roomId, $payload);

        dispatch(static function () use ($event): void {
            if (config('broadcasting.default') === 'null') {
                return;
            }

            try {
                broadcast($event);
            } catch (Throwable $e) {
                Log::warning('staff_team_chat.broadcast_failed', [
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
            Log::warning('staff_team_chat.broadcast_failed', [
                'event' => $event::class,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
