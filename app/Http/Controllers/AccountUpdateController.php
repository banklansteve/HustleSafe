<?php

namespace App\Http\Controllers;

use App\Http\Requests\Account\AccountDetailsUpdateRequest;
use App\Http\Requests\Account\AccountPowerHoursUpdateRequest;
use App\Http\Requests\Account\AccountSkillsUpdateRequest;
use App\Http\Requests\Account\AccountVisibilityUpdateRequest;
use App\Services\Matching\FreelancerMetricsService;
use App\Services\Quest\QuestSkillDictionaryService;
use App\Jobs\ScanContentForModerationJob;
use App\Models\User;
use App\Services\PowerHoursService;
use App\Services\TrustScoreOrchestrator;
use App\Support\TextCasing;
use Illuminate\Http\RedirectResponse;

class AccountUpdateController extends Controller
{
    public function details(AccountDetailsUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $data = TextCasing::patchUserProfile(
            $data,
            ['first_name', 'last_name', 'name', 'headline', 'city', 'profession', 'job_title', 'company_name'],
            ['address_line', 'bio'],
        );
        $user->fill(array_filter($data, fn ($v) => $v !== null && $v !== ''));
        $user->save();
        $trustScores->recalculate($user->fresh());
        ScanContentForModerationJob::dispatch(User::class, (int) $user->id)->afterResponse();

        return redirect()->route('account.show', ['tab' => 'overview'])->with('success', __('Profile updated.'));
    }

    public function visibility(AccountVisibilityUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $patch = $request->validatedSettings();
        $merged = array_merge($user->public_profile_settings ?? [], $patch);
        unset($merged['show_phone'], $merged['show_email']);
        $user->public_profile_settings = $merged;
        $user->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('account.show', ['tab' => 'visibility'])->with('success', __('Public visibility saved.'));
    }

    public function skills(
        AccountSkillsUpdateRequest $request,
        FreelancerMetricsService $metrics,
        TrustScoreOrchestrator $trustScores,
        QuestSkillDictionaryService $dictionary,
    ): RedirectResponse {
        $user = $request->user();
        $user->loadMissing('questCategoryPreferences:id');

        $categoryIds = $user->questCategoryPreferences->pluck('id')->map(fn ($id) => (int) $id)->all();

        $rawSkills = collect($request->validated()['skills'] ?? [])
            ->map(fn ($skill) => trim((string) $skill))
            ->filter()
            ->unique(fn ($skill) => mb_strtolower($skill))
            ->take(30)
            ->values()
            ->all();

        $skills = [];
        $invalid = [];

        foreach ($rawSkills as $skill) {
            $canonical = $dictionary->resolveCanonical($skill, $categoryIds);
            if ($canonical === null) {
                $invalid[] = $skill;

                continue;
            }

            $skills[] = $canonical;
        }

        if ($invalid !== []) {
            return redirect()
                ->route('account.show', ['tab' => 'overview'])
                ->withErrors([
                    'skills' => __('Pick skills from the suggestions list so they match client quest requirements. Not recognized: :list', [
                        'list' => implode(', ', array_slice($invalid, 0, 5)),
                    ]),
                ]);
        }

        $settings = $user->public_profile_settings ?? [];
        $settings['skills'] = $skills;
        $user->public_profile_settings = $settings;
        $user->save();

        // Refresh matching metrics immediately so new skills affect quest matching now,
        // instead of waiting for the periodic metrics refresh.
        $metrics->refresh($user->fresh());
        $trustScores->recalculate($user->fresh());

        return redirect()->route('account.show', ['tab' => 'overview'])->with('success', __('Skills updated.'));
    }

    public function powerHours(AccountPowerHoursUpdateRequest $request, PowerHoursService $powerHours, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'power_hours' => $powerHours->normalize($request->validated()),
        ])->save();

        $trustScores->recalculate($user->fresh());

        return redirect()->route('account.show', ['tab' => 'overview'])
            ->with('success', __('Power Hours availability saved.'));
    }
}
