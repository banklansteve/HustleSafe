<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreContentReportRequest;
use App\Models\ContentReport;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Moderation\ContentModerationScannerService;
use Illuminate\Http\RedirectResponse;

class QuestContentReportController extends Controller
{
    public function storeQuest(StoreContentReportRequest $request, Quest $quest, ContentModerationScannerService $moderation): RedirectResponse
    {
        $this->authorize('view', $quest);

        $case = $moderation->createFromReport(
            $quest,
            $request->user(),
            $request->validated()['reason'],
            $request->validated()['details'] ?? null,
            $request->validated()['severity'] ?? 'warning',
        );

        ContentReport::query()->create([
            'moderation_case_id' => $case->id,
            'user_id' => $request->user()->id,
            'reportable_type' => Quest::class,
            'reportable_id' => $quest->id,
            'reason' => $request->validated()['reason'],
            'details' => $request->validated()['details'] ?? null,
            'severity' => $request->validated()['severity'] ?? 'standard',
            'intake_channel' => 'in_app',
            'status' => 'open',
        ]);

        return back()->with('success', __('Thanks — our team will review this report.'));
    }

    public function storeProposal(StoreContentReportRequest $request, Quest $quest, QuestOffer $offer, ContentModerationScannerService $moderation): RedirectResponse
    {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $user = $request->user();
        if (! in_array($user->role?->slug, ['admin', 'super_admin'], true)) {
            if ((int) $user->id !== (int) $offer->freelancer_id && (int) $user->id !== (int) $quest->client_id) {
                abort(403);
            }
        }

        $this->authorize('view', $offer);

        $case = $moderation->createFromReport(
            $offer,
            $request->user(),
            $request->validated()['reason'],
            $request->validated()['details'] ?? null,
            $request->validated()['severity'] ?? 'warning',
        );

        ContentReport::query()->create([
            'moderation_case_id' => $case->id,
            'user_id' => $request->user()->id,
            'reportable_type' => QuestOffer::class,
            'reportable_id' => $offer->id,
            'reason' => $request->validated()['reason'],
            'details' => $request->validated()['details'] ?? null,
            'severity' => $request->validated()['severity'] ?? 'standard',
            'intake_channel' => 'in_app',
            'status' => 'open',
        ]);

        return back()->with('success', __('Thanks — our team will review this report.'));
    }
}
