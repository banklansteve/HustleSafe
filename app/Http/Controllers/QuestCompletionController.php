<?php

namespace App\Http\Controllers;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Services\Payments\EscrowPaymentService;
use App\Services\QuestCompletionEventLogger;
use App\Support\EscrowReleasePolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestCompletionController extends Controller
{
    public function __construct(
        private readonly EscrowPaymentService $escrowPayments,
        private readonly QuestCompletionEventLogger $events,
    ) {}

    public function acknowledgeDelivery(Request $request, Quest $quest): RedirectResponse
    {
        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        $request->validate(['confirm' => ['accepted']]);

        if (! EscrowReleasePolicy::canAcknowledgeDelivery($quest, $request->user())) {
            throw ValidationException::withMessages(['quest' => [__('Delivery cannot be confirmed right now.')]]);
        }

        $quest->update([
            'delivery_acknowledged_at' => now(),
            'delivery_acknowledged_by' => $request->user()->id,
        ]);

        $this->events->record($quest->fresh(), 'delivery_acknowledged', $request->user(), $request, [
            'escrow_status' => $quest->escrow_status,
        ]);

        $quest->refresh();
        if (EscrowReleasePolicy::requiresSuperAdminAuthorization($quest) && ! EscrowReleasePolicy::hasSuperAdminAuthorization($quest)) {
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

        return back()->with('success', __('Delivery acknowledged. Escrow stays protected until the release window opens and any required authorisations are in place.'))
            ->with('sla_expectation', EscrowReleasePolicy::requiresSuperAdminAuthorization($quest)
                ? app(\App\Services\Platform\PlatformSlaService::class)->userExpectationMessage('escrow_release_appeal')
                : null);
    }

    public function releaseFunds(Request $request, Quest $quest): RedirectResponse
    {
        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        $request->validate([
            'confirm' => ['accepted'],
            'acknowledge_release' => ['accepted'],
        ]);

        if ($quest->status !== QuestStatus::InProgress) {
            throw ValidationException::withMessages(['quest' => [__('Funds cannot be released for this quest right now.')]]);
        }

        if ($quest->delivery_acknowledged_at === null) {
            throw ValidationException::withMessages(['quest' => [__('Confirm delivery before releasing funds.')]]);
        }

        if (! EscrowReleasePolicy::canReleaseFunds($quest, $request->user())) {
            throw ValidationException::withMessages([
                'escrow' => [EscrowReleasePolicy::blockedReleaseReason($quest, $request->user())],
            ]);
        }

        try {
            $this->escrowPayments->releaseEscrowToWallet($quest->fresh(), $request->user(), __('Client released escrow after delivery confirmation'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $quest->refresh();
        $quest->update([
            'status' => QuestStatus::Completed,
            'completed_at' => now(),
            'funds_released_at' => now(),
            'completed_on_time' => true,
            'closure_type' => 'client_released_funds',
        ]);

        $this->events->record($quest->fresh(), 'funds_released', $request->user(), $request, [
            'closure_type' => 'client_released_funds',
        ]);

        return back()->with('success', __('Escrow released to the freelancer\'s wallet. This quest is now complete.'));
    }

    /** @deprecated Use acknowledgeDelivery + releaseFunds */
    public function markComplete(Request $request, Quest $quest): RedirectResponse
    {
        if ($quest->delivery_acknowledged_at === null) {
            return $this->acknowledgeDelivery($request, $quest);
        }

        return $this->releaseFunds($request, $quest);
    }
}
