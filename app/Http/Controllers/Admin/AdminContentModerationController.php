<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreModerationDecisionRequest;
use App\Http\Requests\Admin\UpdateModerationSettingsRequest;
use App\Http\Requests\Admin\UpsertModerationKeywordRequest;
use App\Models\ModerationCase;
use App\Models\ModerationKeyword;
use App\Models\ModerationNotificationTemplate;
use App\Models\ModerationSetting;
use App\Services\Admin\ContentModerationAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminContentModerationController extends Controller
{
    public function __construct(private readonly ContentModerationAdminService $moderation) {}

    public function index(Request $request): Response
    {
        $section = (string) $request->query('section', 'quests');
        if (! in_array($section, ['quests', 'profiles', 'reviews', 'history', 'settings'], true)) {
            $section = 'quests';
        }

        return Inertia::render('Admin/ContentModeration/Index', [
            'section' => $section,
            'summary' => $this->moderation->summary(),
            'queue' => fn () => in_array($section, ['quests', 'profiles', 'reviews'], true)
                ? $this->moderation->queue($request, $section)
                : ['data' => []],
            'history' => fn () => $section === 'history' ? $this->moderation->history($request) : ['data' => []],
            'settings' => fn () => $section === 'settings' ? $this->moderation->settings() : null,
            'metrics' => fn () => $this->moderation->metrics(),
            'filters' => $request->only(['q', 'severity', 'sort', 'per_page']),
            'reasonOptions' => [
                'prohibited_content',
                'off_platform_solicitation',
                'spam',
                'fraudulent_posting',
                'inappropriate_image',
                'review_manipulation',
                'personal_information',
                'policy_violation',
            ],
        ]);
    }

    public function show(ModerationCase $case): JsonResponse
    {
        return response()->json($this->moderation->casePayload($case->load('moderatable')));
    }

    public function decide(StoreModerationDecisionRequest $request, ModerationCase $case): JsonResponse
    {
        $decision = $this->moderation->decide($case, $request->user(), $request->validated());

        return response()->json(['ok' => true, 'decision_id' => $decision->id]);
    }

    public function storeKeyword(UpsertModerationKeywordRequest $request): RedirectResponse
    {
        ModerationKeyword::query()->create($request->validated() + ['is_active' => $request->boolean('is_active', true)]);

        return back()->with('success', 'Moderation keyword added.');
    }

    public function updateKeyword(UpsertModerationKeywordRequest $request, ModerationKeyword $keyword): RedirectResponse
    {
        $keyword->update($request->validated() + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Moderation keyword updated.');
    }

    public function destroyKeyword(ModerationKeyword $keyword): RedirectResponse
    {
        $keyword->update(['is_active' => false]);

        return back()->with('success', 'Moderation keyword paused.');
    }

    public function updateSettings(UpdateModerationSettingsRequest $request): RedirectResponse
    {
        $data = $request->validated();
        foreach (['new_account_review_hours', 'allowed_external_domains', 'cloudinary_moderation_enabled'] as $key) {
            ModerationSetting::query()->updateOrCreate(['key' => $key], ['value' => $data[$key] ?? false]);
        }

        foreach ($data['templates'] ?? [] as $template) {
            ModerationNotificationTemplate::query()
                ->where('key', $template['key'])
                ->update([
                    'subject' => $template['subject'] ?? null,
                    'body' => $template['body'],
                ]);
        }

        return back()->with('success', 'Moderation settings saved.');
    }
}
