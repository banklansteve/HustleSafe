<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountReactivateController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($user->deactivated_at === null) {
            throw ValidationException::withMessages([
                'email' => __('This account is already active.'),
            ]);
        }

        $user->forceFill(['deactivated_at' => null])->save();

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.show')->with('success', __('Welcome back — your account is active again.'));
    }
}
