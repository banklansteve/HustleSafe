<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\UpdateOperationsUserSuspensionRequest;
use App\Models\User;
use App\Services\Operations\StaffUserManagementService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsUsersController extends Controller
{
    public function index(Request $request, StaffUserManagementService $service): Response
    {
        $payload = $service->listing($request);

        return Inertia::render('Operations/Users/Index', [
            'users' => $payload['items'],
            'meta' => $payload['meta'],
            'tags' => $service->tags(),
        ]);
    }

    public function listing(Request $request, StaffUserManagementService $service): JsonResponse
    {
        return response()->json($service->listing($request));
    }

    public function profile(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        return response()->json($service->profile($user, (string) $request->input('tab', 'overview')));
    }

    public function storeNote(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:5000'],
            'share_with_admins' => ['sometimes', 'boolean'],
        ]);

        $note = $service->storeNote($user, $request->user(), $data, $request);

        return response()->json(['ok' => true, 'note_id' => $note->id]);
    }

    public function warning(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(['fraud_risk', 'abuse_or_harassment', 'payment_risk', 'identity_mismatch', 'policy_violation', 'dispute_pattern'])],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $service->issueWarning($user, $request->user(), $data, $request);

        return response()->json(['ok' => true]);
    }

    public function suspend(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'reason_code' => ['required', Rule::in(['fraud_risk', 'abuse_or_harassment', 'payment_risk', 'identity_mismatch', 'policy_violation', 'dispute_pattern'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'ends_at' => ['nullable', 'date', 'after:now'],
        ]);

        $service->suspend($user, $request->user(), $data, $request);

        return response()->json(['ok' => true]);
    }

    public function unsuspend(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $service->unsuspend($user, $request->user(), $request);

        return response()->json(['ok' => true]);
    }

    public function flag(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $service->flagForReview($user, $request->user(), $data, $request);

        return response()->json(['ok' => true]);
    }

    public function syncTags(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'tag_ids' => ['array'],
            'tag_ids.*' => ['integer', 'exists:admin_user_tags,id'],
        ]);

        $service->syncTags($user, $request->user(), $data['tag_ids'] ?? [], $request);

        return response()->json(['ok' => true]);
    }

    public function message(User $user, Request $request, StaffUserManagementService $service): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $service->message($user, $request->user(), $data, $request);

        return response()->json(['ok' => true]);
    }

    public function updateSuspension(UpdateOperationsUserSuspensionRequest $request, User $user, StaffUserManagementService $service): RedirectResponse
    {
        if ((bool) $request->validated('suspended')) {
            $service->suspend($user, $request->user(), ['reason_code' => 'policy_violation'], $request);
        } else {
            $service->unsuspend($user, $request->user(), $request);
        }

        return back()->with('success', (bool) $request->validated('suspended') ? __('Account suspended.') : __('Suspension cleared.'));
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

        $header = ['id', 'name', 'email', 'username', 'role_slug', 'suspended_at', 'created_at', 'last_active_at'];

        return AdminCsv::download('operations-users-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(300, function ($users) use ($out): void {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name,
                        $user->email,
                        $user->username,
                        $user->role?->slug,
                        $user->suspended_at?->toIso8601String(),
                        $user->created_at?->toIso8601String(),
                        $user->last_active_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
