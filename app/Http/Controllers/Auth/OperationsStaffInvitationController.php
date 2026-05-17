<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\StoreOperationsStaffPasswordSetupRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

class OperationsStaffInvitationController extends Controller
{
    public function show(User $user): Response|RedirectResponse
    {
        if ($user->role?->slug !== 'admin') {
            abort(404);
        }

        if (Auth::check() && Auth::id() !== $user->id) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Auth/OperationsStaffActivatePassword', [
            'email' => $user->email,
            'post_url' => URL::temporarySignedRoute(
                'operations.invitation.update',
                now()->addDays(7),
                ['user' => $user->id]
            ),
        ]);
    }

    public function update(StoreOperationsStaffPasswordSetupRequest $request, User $user): RedirectResponse
    {
        if ($user->role?->slug !== 'admin') {
            abort(404);
        }

        $user->forceFill([
            'password' => $request->validated('password'),
            'operations_staff_password_set_at' => now(),
        ])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('operations.dashboard')
            ->with('success', __('Your password is set — welcome to the operations console.'));
    }
}
