<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreOperationsStaffPasswordSetupRequest;
use App\Models\User;
use App\Support\RoleSessionLifetime;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class AdminStaffInvitationController extends Controller
{
    public function show(User $user): Response|RedirectResponse
    {
        abort_unless($this->isAdminUser($user), 404);

        if (Auth::check() && Auth::id() !== $user->id) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/AdminStaffActivatePassword', [
            'email' => $user->email,
            'name' => $user->name,
            'post_url' => URL::temporarySignedRoute(
                'admin.invitation.update',
                now()->addDays(7),
                ['user' => $user->id]
            ),
        ]);
    }

    public function update(StoreOperationsStaffPasswordSetupRequest $request, User $user): RedirectResponse
    {
        abort_unless($this->isAdminUser($user), 404);

        $user->forceFill([
            'password' => $request->validated('password'),
            'email_verified_at' => $user->email_verified_at ?? now(),
            'operations_staff_password_set_at' => $user->role?->slug === 'admin'
                ? now()
                : $user->operations_staff_password_set_at,
        ])->save();

        Auth::login($user);
        RoleSessionLifetime::applyForRole($user->role?->slug);
        $request->session()->regenerate();

        $destination = $user->role?->slug === 'admin'
            ? route('operations.dashboard')
            : route('admin.dashboard');

        return redirect()->to($destination)
            ->with('success', __('Your admin password is set. Welcome to the dashboard.'));
    }

    private function isAdminUser(User $user): bool
    {
        $user->loadMissing('role');

        return in_array($user->role?->slug, ['admin', 'super_admin'], true)
            || $user->account_type === 'admin';
    }
}
