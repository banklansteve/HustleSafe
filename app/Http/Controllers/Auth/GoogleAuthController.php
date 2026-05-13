<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google OAuth, or back to login/register with a message when not configured.
     *
     * @param  Request  $request  Optional query `return` = login|register chooses the fallback route.
     */
    public function redirect(Request $request): RedirectResponse|Response
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            $return = $request->query('return');
            $route = $return === 'register' ? 'register' : 'login';

            return redirect()->route($route)->with('status', __('auth.google_unavailable'));
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google.
     */
    public function callback(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->with('status', __('Google sign-in is not available.'));
        }

        $googleUser = Socialite::driver('google')->user();

        $fullName = trim((string) ($googleUser->getName() ?? ''));
        $parts = $fullName !== '' ? preg_split('/\s+/', $fullName, 2) : ['', ''];
        $firstName = $parts[0] ?: 'User';
        $lastName = $parts[1] ?? '';

        $user = User::query()->where('google_id', $googleUser->getId())->first();

        if ($user === null) {
            $user = User::query()->where('email', $googleUser->getEmail())->first();

            if ($user !== null) {
                $user->forceFill([
                    'google_id' => $googleUser->getId(),
                    'avatar_url' => $googleUser->getAvatar(),
                ])->save();
            }
        }

        if ($user === null) {
            $displayName = $fullName !== '' ? $fullName : trim($firstName.' '.$lastName);

            $user = User::create([
                'name' => $displayName !== '' ? $displayName : $firstName,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $googleUser->getEmail(),
                'password' => Hash::make(Str::random(64)),
                'google_id' => $googleUser->getId(),
                'avatar_url' => $googleUser->getAvatar(),
                'account_type' => 'sponsor',
                'email_verified_at' => now(),
            ]);
        }

        if ($user->isDeactivated()) {
            return redirect()->route('login')->with(
                'status',
                __('Your account is deactivated. Use "Reactivate account" on the login page with your email and password.')
            );
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
