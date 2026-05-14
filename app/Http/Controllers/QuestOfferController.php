<?php

namespace App\Http\Controllers;

use App\Events\QuestProposalListUpdated;
use App\Http\Requests\Quests\StoreQuestProposalRequest;
use App\Http\Requests\Quests\UpdateQuestProposalRequest;
use App\Jobs\DeliverQuestOfferClientNotification;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\FreelancerWorkspaceReadinessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;

class QuestOfferController extends Controller
{
    public function store(
        StoreQuestProposalRequest $request,
        Quest $quest,
        FreelancerWorkspaceReadinessService $readiness,
    ): RedirectResponse {
        $this->authorize('view', $quest);

        $user = $request->user();
        $payload = $request->normalizedPayload();
        $grand = (int) ($payload['pricing_snapshot']['grand_total_minor'] ?? 0);
        $readiness->assertCanSubmitOffer($user, $quest, $grand);

        $validated = $request->validated();

        $estimatedDurationDays = $validated['estimated_duration_days'] ?? null;
        if ($estimatedDurationDays === null) {
            try {
                $estimatedDurationDays = max(1, Carbon::parse($validated['planned_start_date'])
                    ->diffInDays(Carbon::parse($validated['planned_finish_date'])));
            } catch (\Throwable) {
                $estimatedDurationDays = null;
            }
        }

        $editHours = max(1, (int) config('quests.proposal_freelancer_edit_hours', 48));

        $offer = QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $user->id,
            'pitch' => $validated['pitch'],
            'scope_detail' => $validated['scope_detail'],
            'warranty_terms' => $validated['warranty_terms'] ?? null,
            'proposed_completion_date' => $validated['planned_finish_date'],
            'planned_start_date' => $validated['planned_start_date'],
            'planned_finish_date' => $validated['planned_finish_date'],
            'estimated_duration_days' => $estimatedDurationDays,
            'corrections_included' => (bool) ($validated['corrections_included'] ?? false),
            'corrections_rounds' => ($validated['corrections_included'] ?? false) ? ($validated['corrections_rounds'] ?? null) : null,
            'progress_report_frequency' => $validated['progress_report_frequency'] ?? null,
            'materials' => $payload['materials'],
            'pricing_snapshot' => $payload['pricing_snapshot'],
            'quoted_amount_minor' => $grand,
            'status' => 'submitted',
            'freelancer_edit_deadline_at' => now()->addHours($editHours),
        ]);

        $quest->increment('offers_count');

        broadcast(new QuestProposalListUpdated((int) $quest->id));

        DeliverQuestOfferClientNotification::dispatch($offer->id, 'new')->afterResponse();

        return redirect()
            ->route('quests.proposals.show', [$quest, $offer])
            ->with('success', __('Proposal submitted — the client has been notified.'))
            ->with('proposal_next_steps', true);
    }

    public function update(
        UpdateQuestProposalRequest $request,
        Quest $quest,
        QuestOffer $offer,
        FreelancerWorkspaceReadinessService $readiness,
    ): RedirectResponse {
        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        $this->authorize('update', $offer);

        $payload = $request->normalizedPayload();
        $grand = (int) ($payload['pricing_snapshot']['grand_total_minor'] ?? 0);
        $readiness->assertCanSubmitOffer($request->user(), $quest, $grand);
        $validated = $request->validated();

        $estimatedDurationDays = $validated['estimated_duration_days'] ?? null;
        if ($estimatedDurationDays === null) {
            try {
                $estimatedDurationDays = max(1, Carbon::parse($validated['planned_start_date'])
                    ->diffInDays(Carbon::parse($validated['planned_finish_date'])));
            } catch (\Throwable) {
                $estimatedDurationDays = null;
            }
        }

        $offer->update([
            'pitch' => $validated['pitch'],
            'scope_detail' => $validated['scope_detail'],
            'warranty_terms' => $validated['warranty_terms'] ?? null,
            'proposed_completion_date' => $validated['planned_finish_date'],
            'planned_start_date' => $validated['planned_start_date'],
            'planned_finish_date' => $validated['planned_finish_date'],
            'estimated_duration_days' => $estimatedDurationDays,
            'corrections_included' => (bool) ($validated['corrections_included'] ?? false),
            'corrections_rounds' => ($validated['corrections_included'] ?? false) ? ($validated['corrections_rounds'] ?? null) : null,
            'progress_report_frequency' => $validated['progress_report_frequency'] ?? null,
            'materials' => $payload['materials'],
            'pricing_snapshot' => $payload['pricing_snapshot'],
            'quoted_amount_minor' => $grand,
        ]);

        DeliverQuestOfferClientNotification::dispatch($offer->id, 'updated')->afterResponse();

        return redirect()
            ->route('quests.proposals.show', [$quest, $offer])
            ->with('success', __('Proposal updated — the client has been notified.'));
    }
}
