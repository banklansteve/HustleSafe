<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountDeactivateController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'confirm' => ['required', 'in:DEACTIVATE'],
        ], [
            'confirm.in' => __('Type DEACTIVATE to confirm.'),
        ]);

        $user = $request->user();
        $user->forceFill(['deactivated_at' => now()])->save();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', __('Your account is deactivated. You can reactivate by signing in with your email and password, then confirming reactivation.'));
    }
}
