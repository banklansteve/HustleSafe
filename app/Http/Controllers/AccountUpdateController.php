<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AccountDetailsUpdateRequest;
use App\Http\Requests\Account\AccountVisibilityUpdateRequest;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Http\RedirectResponse;

class AccountUpdateController extends Controller
{
    public function details(AccountDetailsUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $user->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $user->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('account.show', ['tab' => 'overview'])->with('success', __('Profile updated.'));
    }

    public function visibility(AccountVisibilityUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $patch = $request->validatedSettings();
        $merged = array_merge($user->public_profile_settings ?? [], $patch);
        $user->public_profile_settings = $merged;
        $user->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('account.show', ['tab' => 'visibility'])->with('success', __('Public visibility saved.'));
    }
}
