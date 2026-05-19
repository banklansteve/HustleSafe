<?php

namespace App\Services\Operations;

use App\Models\ActivityLog;
use App\Models\AdminUserNote;
use App\Models\AdminUserSanction;
use App\Models\AdminUserTag;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\Admin\AdvancedUserManagementService;
use App\Services\AdminActivityLogger;
use App\Support\Operations\StaffCapabilities;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StaffUserManagementService
{
    public function __construct(
        private readonly AdvancedUserManagementService $users,
        private readonly AdminActivityLogger $logger,
    ) {}

    public function listing(Request $request): array
    {
        $paginator = $this->users->paginated($request->merge(['per_page' => min(250, max(25, $request->integer('per_page', 100)))]));

        return [
            'items' => collect($paginator->items())->values()->all(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
            ],
        ];
    }

    public function profile(User $user, string $tab = 'overview'): array
    {
        $this->guardMemberAccount($user);

        return $this->users->profile($user, $tab);
    }

    public function storeNote(User $user, User $staff, array $data, Request $request): AdminUserNote
    {
        $this->guardMemberAccount($user);

        $note = AdminUserNote::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $staff->id,
            'body' => $data['body'],
            'context' => ['share_with_admins' => (bool) ($data['share_with_admins'] ?? true)],
        ]);

        ActivityLog::query()->create([
            'subject_user_id' => $user->id,
            'actor_id' => $staff->id,
            'type' => 'operations.user_note.created',
            'title' => 'Staff admin note',
            'body' => Str::limit($data['body'], 180),
            'meta' => ['note_id' => $note->id],
            'created_at' => now(),
        ]);

        $this->logger->log($staff, 'operations.user.note_created', User::class, $user->id, ['note_id' => $note->id], $request);

        return $note;
    }

    public function issueWarning(User $user, User $staff, array $data, Request $request): AdminUserSanction
    {
        $this->guardMemberAccount($user);

        return $this->createSanction($user, $staff, 'warning', $data, $request);
    }

    public function suspend(User $user, User $staff, array $data, Request $request): void
    {
        $this->guardMemberAccount($user);

        $endsAt = now()->addHours(StaffCapabilities::maxSuspensionHours());
        if (! empty($data['ends_at'])) {
            $requested = Carbon::parse($data['ends_at']);
            if ($requested->gt($endsAt)) {
                throw ValidationException::withMessages([
                    'ends_at' => __('Staff suspensions cannot exceed :hours hours without Super Admin approval.', [
                        'hours' => StaffCapabilities::maxSuspensionHours(),
                    ]),
                ]);
            }
            $endsAt = $requested;
        }

        $this->createSanction($user, $staff, 'suspension', [
            ...$data,
            'ends_at' => $endsAt,
        ], $request);

        $user->forceFill(['suspended_at' => now()])->save();
    }

    public function unsuspend(User $user, User $staff, Request $request): void
    {
        $this->guardMemberAccount($user);
        $user->forceFill(['suspended_at' => null])->save();

        $this->logger->log($staff, 'operations.user.unsuspended', User::class, $user->id, [], $request);
    }

    public function flagForReview(User $user, User $staff, array $data, Request $request): void
    {
        $this->guardMemberAccount($user);
        $user->forceFill(['under_review_at' => now()])->save();

        $this->logger->log($staff, 'operations.user.flagged_for_review', User::class, $user->id, [
            'reason' => $data['reason'] ?? null,
        ], $request);
    }

    public function syncTags(User $user, User $staff, array $tagIds, Request $request): void
    {
        $this->guardMemberAccount($user);
        $user->adminTags()->sync($tagIds);

        $this->logger->log($staff, 'operations.user.tags_updated', User::class, $user->id, [
            'tag_ids' => $tagIds,
        ], $request);
    }

    public function message(User $user, User $staff, array $data, Request $request): void
    {
        $this->guardMemberAccount($user);
        $user->notify(new AdminUserMessageNotification($data['subject'], $data['body']));

        $this->logger->log($staff, 'operations.user.message_sent', User::class, $user->id, [
            'subject' => $data['subject'],
        ], $request);
    }

    /**
     * @return list<array{id: int, name: string, color: string|null}>
     */
    public function tags(): array
    {
        return AdminUserTag::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color'])
            ->map(fn (AdminUserTag $tag) => ['id' => $tag->id, 'name' => $tag->name, 'color' => $tag->color])
            ->values()
            ->all();
    }

    private function createSanction(User $user, User $staff, string $type, array $data, Request $request): AdminUserSanction
    {
        return DB::transaction(function () use ($user, $staff, $type, $data, $request): AdminUserSanction {
            $sanction = AdminUserSanction::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $staff->id,
                'type' => $type,
                'reason_code' => $data['reason_code'] ?? 'policy_violation',
                'notes' => $data['notes'] ?? ($data['reason'] ?? null),
                'starts_at' => now(),
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            ActivityLog::query()->create([
                'subject_user_id' => $user->id,
                'actor_id' => $staff->id,
                'type' => 'operations.user.'.$type,
                'title' => 'User '.$type,
                'body' => Str::limit((string) ($data['notes'] ?? $data['reason'] ?? ''), 180),
                'meta' => ['sanction_id' => $sanction->id],
                'created_at' => now(),
            ]);

            $this->logger->log($staff, 'operations.user.'.$type, User::class, $user->id, [
                'sanction_id' => $sanction->id,
            ], $request);

            return $sanction;
        });
    }

    private function guardMemberAccount(User $user): void
    {
        if (in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            throw ValidationException::withMessages([
                'user' => __('Staff cannot modify other admin accounts from this console.'),
            ]);
        }
    }
}
