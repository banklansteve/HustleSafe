<?php

namespace App\Http\Controllers;

use App\Enums\QuestStatus;
use App\Http\Requests\Quests\RequestDeliveryRevisionRequest;
use App\Http\Requests\Quests\StoreQuestDeliverySubmissionRequest;
use App\Models\Quest;
use App\Notifications\QuestDeliverableSubmittedNotification;
use App\Notifications\QuestDeliveryRevisionRequestedNotification;
use App\Services\Quest\QuestDeliveryLifecycleService;
use App\Services\QuestCompletionEventLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestDeliverySubmissionController extends Controller
{
    public function __construct(
        private readonly QuestDeliveryLifecycleService $lifecycle,
        private readonly QuestCompletionEventLogger $events,
    ) {}

    public function store(StoreQuestDeliverySubmissionRequest $request, Quest $quest): RedirectResponse
    {
        $user = $request->user();
        $submission = $this->lifecycle->submitDeliverable($quest, $user, $request->validated());

        $quest = $quest->fresh(['client', 'freelancer']);
        $this->events->record($quest, 'deliverable_submitted', $user, $request, [
            'submission_id' => $submission->id,
            'revision_number' => $submission->revision_number,
        ]);

        $quest->client?->notify(new QuestDeliverableSubmittedNotification($quest, $submission));

        return back()->with('success', __('Deliverable submitted. The client has :hours hours to review before auto-release may apply.', [
            'hours' => $this->lifecycle->reviewHours(),
        ]));
    }

    public function requestRevision(RequestDeliveryRevisionRequest $request, Quest $quest): RedirectResponse
    {
        $user = $request->user();
        $this->lifecycle->requestRevision($quest, $user, (string) $request->validated('note'));

        $quest = $quest->fresh(['freelancer']);
        $this->events->record($quest, 'delivery_revision_requested', $user, $request, [
            'note' => $request->validated('note'),
        ]);

        $quest->freelancer?->notify(new QuestDeliveryRevisionRequestedNotification($quest, (string) $request->validated('note')));

        return back()->with('success', __('Revision request sent. The freelancer can resubmit when ready.'));
    }

    public function approve(Request $request, Quest $quest): RedirectResponse
    {
        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        $request->validate(['confirm' => ['accepted']]);

        if (! $this->lifecycle->canClientApprove($quest, $request->user())) {
            throw ValidationException::withMessages(['quest' => [__('You cannot approve this deliverable right now.')]]);
        }

        $recurring = app(\App\Services\Quest\QuestRecurringEngagementService::class);
        if ($recurring->isRecurring($quest)) {
            $installment = $recurring->currentInstallment($quest);
            if ($installment !== null) {
                $recurring->markInstallmentApproved($quest, $installment, $request->user());
            }
        } else {
            $quest->update([
                'delivery_acknowledged_at' => now(),
                'delivery_acknowledged_by' => $request->user()->id,
            ]);
        }

        $this->events->record($quest->fresh(), 'delivery_approved', $request->user(), $request, []);

        $quest->refresh();

        if (\App\Support\EscrowReleasePolicy::canReleaseFunds($quest, $request->user())) {
            $request->merge(['acknowledge_release' => true]);

            return app(QuestCompletionController::class)->releaseFunds($request, $quest);
        }

        if (\App\Support\EscrowReleasePolicy::requiresSuperAdminAuthorization($quest)
            && ! \App\Support\EscrowReleasePolicy::hasSuperAdminAuthorization($quest)) {
            app(\App\Services\Platform\PlatformSlaService::class)->start(
                'escrow_release_appeal',
                $quest,
                null,
                $request->user(),
                [
                    'subject_label' => $quest->title ?? "Quest #{$quest->id}",
                    'quest_id' => $quest->id,
                ],
            );
        }

        return back()->with('success', __('Deliverable approved. Payment will release when the safeguard window and any authorisations are satisfied.'))
            ->with('sla_expectation', \App\Support\EscrowReleasePolicy::requiresSuperAdminAuthorization($quest)
                ? app(\App\Services\Platform\PlatformSlaService::class)->userExpectationMessage('escrow_release_appeal')
                : null);
    }
}
