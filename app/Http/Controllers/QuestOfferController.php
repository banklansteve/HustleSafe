<?php

namespace App\Http\Controllers;

use App\Events\QuestProposalListUpdated;
use App\Http\Requests\Quests\StoreQuestProposalRequest;
use App\Http\Requests\Quests\UpdateQuestProposalRequest;
use App\Jobs\ScanContentForModerationJob;
use App\Jobs\DeliverQuestOfferClientNotification;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\FreelancerWorkspaceReadinessService;
use App\Services\Verification\VerificationEngineService;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class QuestOfferController extends Controller
{
    public function store(
        StoreQuestProposalRequest $request,
        Quest $quest,
        FreelancerWorkspaceReadinessService $readiness,
        VerificationEngineService $verificationEngine,
    ): RedirectResponse {
        $this->authorize('view', $quest);

        $user = $request->user();
        $payload = $request->normalizedPayload();
        $grand = (int) ($payload['pricing_snapshot']['grand_total_minor'] ?? 0);
        $readiness->assertCanSubmitOffer($user, $quest, $grand);
        app(\App\Services\Freelancer\ProposalQuotaService::class)->assertCanSubmit($user, $quest);

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

        try {
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
        } catch (QueryException $exception) {
            if ((string) $exception->getCode() === '23000' || str_contains(strtolower($exception->getMessage()), 'unique')) {
                throw ValidationException::withMessages([
                    'proposal' => __('You already have a proposal on this quest and cannot submit another.'),
                ]);
            }

            throw $exception;
        }

        $quest->increment('offers_count');
        app(\App\Services\Freelancer\ProposalQuotaService::class)->recordSubmission($user);

        defer(function () use ($quest): void {
            try {
                broadcast(new QuestProposalListUpdated((int) $quest->id));
            } catch (\Throwable $exception) {
                report($exception);
            }
        });

        DeliverQuestOfferClientNotification::dispatch($offer->id, 'new')->afterResponse();
        ScanContentForModerationJob::dispatch(QuestOffer::class, (int) $offer->id)->afterResponse();
        $verificationEngine->runAnomalyChecks($user, $quest, $offer);
        if ($verificationEngine->arbitrationRequired($quest, $offer)) {
            $verificationEngine->recordArbitrationAgreement($quest, $offer, $user, 'freelancer');
        }

        if ($grand >= (int) config('quests.high_value_proposal_minor', 5_000_000)) {
            $quest->loadMissing(['client', 'questCategory', 'stateModel']);
            app(AdminActivityFeedService::class)->record(
                'financial',
                'proposal.high_value_submitted',
                'High-value proposal submitted',
                "{$user->name} submitted a proposal on {$quest->title}",
                app(AdminActivityFeedService::class)->entities([
                    ['type' => 'user', 'id' => $user->id, 'label' => $user->name],
                    ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                ]),
                ['category' => $quest->questCategory?->name, 'state' => $quest->stateModel?->name],
                $grand,
                $user,
                QuestOffer::class,
                $offer->id,
                $quest->state_id,
                $quest->local_government_id,
                $quest->quest_category_id,
            );
        }

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
        VerificationEngineService $verificationEngine,
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
        ScanContentForModerationJob::dispatch(QuestOffer::class, (int) $offer->id)->afterResponse();

        return redirect()
            ->route('quests.proposals.show', [$quest, $offer])
            ->with('success', __('Proposal updated — the client has been notified.'));
    }
}
