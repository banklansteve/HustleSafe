<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AdminUserBadge;
use App\Models\AdminUserNote;
use App\Models\AdminUserSanction;
use App\Models\AdminUserSegment;
use App\Models\AdminUserTag;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Services\Admin\AdvancedUserManagementService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminUsersController extends Controller
{
    public function index(Request $request, AdvancedUserManagementService $service): Response
    {
        return Inertia::render('Admin/Users/Index', [
            'users' => $service->paginated($request),
            'filters' => $request->only(['q', 'role', 'status', 'state_id', 'category_id', 'trust_min', 'trust_max', 'joined_from', 'joined_to', 'verified', 'open_disputes', 'flagged', 'per_page']),
            'meta' => $service->meta($request->user()),
        ]);
    }

    public function profile(User $user, Request $request, AdvancedUserManagementService $service): JsonResponse
    {
        return response()->json($service->profile($user, (string) $request->input('tab', 'overview')));
    }

    public function storeNote(User $user, Request $request): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'share_with_admins' => ['sometimes', 'boolean'],
        ]);

        $note = AdminUserNote::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $request->user()->id,
            'body' => $data['body'],
            'context' => ['share_with_admins' => (bool) ($data['share_with_admins'] ?? false)],
        ]);

        ActivityLog::query()->create([
            'subject_user_id' => $user->id,
            'actor_id' => $request->user()->id,
            'type' => 'admin_note.created',
            'title' => 'Admin note added',
            'body' => Str::limit($data['body'], 180),
            'meta' => ['note_id' => $note->id],
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'note_id' => $note->id]);
    }

    public function sanction(User $user, Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['warning', 'restriction', 'suspension', 'ban'])],
            'reason_code' => ['required', Rule::in(['fraud_risk', 'abuse_or_harassment', 'payment_risk', 'identity_mismatch', 'policy_violation', 'dispute_pattern'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $sanction = DB::transaction(function () use ($user, $request, $data): AdminUserSanction {
            $sanction = AdminUserSanction::query()->create([
                'user_id' => $user->id,
                'admin_user_id' => $request->user()->id,
                'type' => $data['type'],
                'reason_code' => $data['reason_code'],
                'notes' => $data['notes'] ?? null,
                'starts_at' => now(),
                'ends_at' => $data['ends_at'] ?? null,
            ]);

            if ($data['type'] === 'suspension') {
                $user->forceFill(['suspended_at' => now()])->save();
            }
            if ($data['type'] === 'ban') {
                $user->forceFill(['banned_at' => now(), 'ban_reason' => $data['notes'] ?? $data['reason_code']])->save();
            }
            if ($data['type'] === 'restriction') {
                $user->forceFill(['under_review_at' => now()])->save();
            }

            ActivityLog::query()->create([
                'subject_user_id' => $user->id,
                'actor_id' => $request->user()->id,
                'type' => 'admin_user.sanctioned',
                'title' => 'User sanction applied',
                'body' => Str::headline($data['type']).' for '.Str::headline($data['reason_code']),
                'meta' => ['sanction_id' => $sanction->id],
                'created_at' => now(),
            ]);

            return $sanction;
        });

        return response()->json(['ok' => true, 'sanction_id' => $sanction->id]);
    }

    public function reverseSanction(User $user, AdminUserSanction $sanction, Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        abort_unless($sanction->user_id === $user->id, 404);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);

        $sanction->forceFill([
            'reversed_at' => now(),
            'reversed_by' => $request->user()->id,
            'reversal_reason' => $data['reason'],
        ])->save();

        if ($sanction->type === 'suspension') {
            $user->forceFill(['suspended_at' => null])->save();
        }
        if ($sanction->type === 'ban') {
            $user->forceFill(['banned_at' => null, 'ban_reason' => null])->save();
        }
        if ($sanction->type === 'restriction') {
            $user->forceFill(['under_review_at' => null])->save();
        }

        ActivityLog::query()->create([
            'subject_user_id' => $user->id,
            'actor_id' => $request->user()->id,
            'type' => 'admin_user.sanction_reversed',
            'title' => 'User sanction reversed',
            'body' => $data['reason'],
            'meta' => ['sanction_id' => $sanction->id],
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    public function saveSegment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
        ]);

        $segment = AdminUserSegment::query()->create([
            'admin_user_id' => $request->user()->id,
            'name' => $data['name'],
            'filters' => $data['filters'],
        ]);

        return response()->json(['segment' => $segment]);
    }

    public function bulk(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['email', 'notification', 'apply_tag', 'remove_tag', 'suspend', 'badge', 'export'])],
            'user_ids' => ['required', 'array', 'min:1', 'max:500'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'message' => ['nullable', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:160'],
            'tag' => ['nullable', 'string', 'max:60'],
            'badge' => ['nullable', 'string', 'max:60'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $users = User::query()->whereKey($data['user_ids'])->get();

        if ($data['action'] === 'export') {
            return $this->exportSelected($users);
        }

        DB::transaction(function () use ($data, $users, $request): void {
            foreach ($users as $user) {
                match ($data['action']) {
                    'apply_tag' => $this->applyTag($user, (string) $data['tag'], $request->user()),
                    'remove_tag' => $this->removeTag($user, (string) $data['tag']),
                    'badge' => $this->assignBadge($user, (string) $data['badge'], $request->user()),
                    'suspend' => $user->forceFill(['suspended_at' => now()])->save(),
                    'notification' => $this->sendAdminMessage($user, $request, false),
                    'email' => $this->sendAdminMessage($user, $request, true),
                    default => null,
                };

                ActivityLog::query()->create([
                    'subject_user_id' => $user->id,
                    'actor_id' => $request->user()->id,
                    'type' => 'admin_user.bulk_'.$data['action'],
                    'title' => 'Bulk user action',
                    'body' => $data['reason'] ?? $data['subject'] ?? $data['message'] ?? $data['tag'] ?? $data['badge'] ?? $data['action'],
                    'meta' => ['action' => $data['action']],
                    'created_at' => now(),
                ]);
            }
        });

        return response()->json(['ok' => true, 'affected' => $users->count()]);
    }

    public function impersonate(User $user, Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        abort_if($user->role?->slug === 'super_admin', 422, 'Super admin accounts cannot be impersonated.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $admin = $request->user();
        $request->session()->put('impersonation', [
            'admin_id' => $admin->id,
            'admin_name' => $admin->name,
            'user_id' => $user->id,
            'user_name' => $user->name,
            'reason' => $data['reason'],
            'started_at' => now()->toIso8601String(),
            'last_activity_at' => now()->toIso8601String(),
        ]);
        Auth::login($user);
        $request->session()->regenerate();

        ActivityLog::query()->create([
            'subject_user_id' => $user->id,
            'actor_id' => $admin->id,
            'type' => 'admin_user.impersonation_started',
            'title' => 'Impersonation started',
            'body' => $data['reason'],
            'meta' => ['impersonated_user_id' => $user->id],
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'redirect' => route('dashboard')]);
    }

    public function stopImpersonating(Request $request): JsonResponse
    {
        $impersonation = $request->session()->get('impersonation');
        abort_unless(is_array($impersonation) && isset($impersonation['admin_id']), 404);

        $admin = User::query()->findOrFail($impersonation['admin_id']);
        Auth::login($admin);
        $request->session()->forget('impersonation');
        $request->session()->regenerate();

        return response()->json(['ok' => true, 'redirect' => route('admin.users.index')]);
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->input('q', ''));
        $query = User::query()->with('role:id,slug');

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('email', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%');
            });
        }

        $header = ['id', 'name', 'email', 'username', 'role_slug', 'created_at', 'last_active_at'];

        return AdminCsv::download('users-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(300, function ($users) use ($out): void {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->username,
                        $user->role?->slug,
                        $user->created_at?->toIso8601String(),
                        $user->last_active_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }

    private function exportSelected($users): StreamedResponse
    {
        $header = ['id', 'name', 'email', 'phone', 'role', 'city', 'state_id', 'created_at', 'last_active_at'];

        return AdminCsv::download('selected-users-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($users): void {
            foreach ($users as $user) {
                fputcsv($out, [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->phone,
                    $user->role?->slug,
                    $user->city,
                    $user->state_id,
                    $user->created_at?->toIso8601String(),
                    $user->last_active_at?->toIso8601String(),
                ]);
            }
        });
    }

    private function applyTag(User $user, string $name, User $admin): void
    {
        if ($name === '') {
            return;
        }

        $tag = AdminUserTag::query()->firstOrCreate(['name' => $name], ['color' => 'teal']);
        $user->adminTags()->syncWithoutDetaching([$tag->id => ['assigned_by' => $admin->id]]);
    }

    private function removeTag(User $user, string $name): void
    {
        $tag = AdminUserTag::query()->where('name', $name)->first();
        if ($tag !== null) {
            $user->adminTags()->detach($tag->id);
        }
    }

    private function assignBadge(User $user, string $name, User $admin): void
    {
        if ($name === '') {
            return;
        }

        $badge = AdminUserBadge::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name]
        );
        $user->adminBadges()->syncWithoutDetaching([$badge->id => ['assigned_by' => $admin->id]]);
    }

    private function sendAdminMessage(User $user, Request $request, bool $asEmail): void
    {
        $message = (string) $request->input('message', '');
        $subject = (string) $request->input('subject', 'Message from HustleSafe Admin');

        if ($asEmail) {
            Mail::raw($message, function ($mail) use ($user, $subject): void {
                $mail->to($user->email)->subject($subject);
            });
        } else {
            $user->notify(new AdminUserMessageNotification($subject, $message));
        }

        AdminUserNote::query()->create([
            'user_id' => $user->id,
            'admin_user_id' => $request->user()->id,
            'body' => ($asEmail ? 'Email sent: ' : 'In-app message sent: ').$subject."\n\n".$message,
            'context' => ['communication' => $asEmail ? 'email' : 'notification'],
        ]);
    }
}
