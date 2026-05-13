<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AvatarUploadRequest;
use App\Services\CloudinaryAvatarService;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountSecurityController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Security', [
            'mustVerifyEmail' => $user instanceof MustVerifyEmail,
            'status' => session('status'),
            'avatarConfigured' => app(CloudinaryAvatarService::class)->isConfigured(),
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function updateAvatar(AvatarUploadRequest $request, CloudinaryAvatarService $cloudinary, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        if (! $cloudinary->isConfigured()) {
            return redirect()
                ->route('account.security.edit')
                ->withErrors(['avatar' => __('Photo upload is not configured yet. Add Cloudinary credentials to your .env file.')]);
        }

        $user = $request->user();
        $url = $cloudinary->uploadAvatar($request->file('avatar'), $user->id);
        $user->forceFill(['avatar_url' => $url])->save();
        $trustScores->recalculate($user->fresh());

        return redirect()
            ->route('account.security.edit')
            ->with('success', __('Profile photo updated.'));
    }
}
