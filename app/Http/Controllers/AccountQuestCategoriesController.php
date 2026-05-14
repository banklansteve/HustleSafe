<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AccountQuestCategoriesUpdateRequest;
use App\Services\TrustScoreOrchestrator;
use Illuminate\Http\RedirectResponse;

class AccountQuestCategoriesController extends Controller
{
    public function update(
        AccountQuestCategoriesUpdateRequest $request,
        TrustScoreOrchestrator $trustScores,
    ): RedirectResponse {
        $user = $request->user();
        $user->questCategoryPreferences()->sync($request->validated()['quest_category_ids']);
        $trustScores->recalculate($user->fresh());

        return redirect()->to(route('account.show', ['tab' => 'overview']).'#account-work-categories')->with('success', __('Work categories updated.'));
    }
}
