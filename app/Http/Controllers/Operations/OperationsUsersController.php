<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\UpdateOperationsUserSuspensionRequest;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Support\AdminCsv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsUsersController extends Controller
{
    public function index(Request $request): Response
    {
        $perPage = min(50, max(5, (int) $request->input('per_page', 15)));
        $q = trim((string) $request->input('q', ''));

        $query = User::query()->with('role:id,name,slug');

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('email', 'like', '%'.$q.'%')
                    ->orWhere('name', 'like', '%'.$q.'%')
                    ->orWhere('username', 'like', '%'.$q.'%');
            });
        }

        $users = $query->orderByDesc('id')->paginate($perPage)->withQueryString();

        return Inertia::render('Operations/Users/Index', [
            'users' => $users,
            'filters' => ['q' => $q, 'per_page' => $perPage],
        ]);
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

    public function updateSuspension(UpdateOperationsUserSuspensionRequest $request, User $user, AdminActivityLogger $logger): RedirectResponse
    {
        $suspended = (bool) $request->validated('suspended');
        $user->forceFill([
            'suspended_at' => $suspended ? now() : null,
        ])->save();

        $logger->log(
            actor: $request->user(),
            action: $suspended ? 'operations.user_suspended' : 'operations.user_unsuspended',
            subjectType: User::class,
            subjectId: $user->id,
            properties: ['email' => $user->email],
            request: $request,
        );

        return back()->with('success', $suspended
            ? __('Account suspended.')
            : __('Suspension cleared.'));
    }
}
