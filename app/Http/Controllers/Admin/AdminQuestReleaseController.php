<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Services\Payments\EscrowPaymentService;
use App\Services\QuestCompletionEventLogger;
use App\Support\EscrowReleasePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminQuestReleaseController extends Controller
{
    public function __construct(
        private readonly QuestCompletionEventLogger $events,
        private readonly EscrowPaymentService $escrow,
    ) {}

    public function authorizeRelease(Request $request, Quest $quest): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $quest->update([
            'release_authorized_at' => now(),
            'release_authorized_by' => $request->user()->id,
        ]);

        app(\App\Services\Platform\PlatformSlaService::class)->resolveForSubject('escrow_release_appeal', $quest);

        $this->events->record($quest->fresh(), 'release_authorized', $request->user(), $request, [
            'reason' => $data['reason'],
            'amount_minor' => EscrowReleasePolicy::escrowAmountMinor($quest),
        ]);

        return response()->json(['ok' => true, 'quest_id' => $quest->id]);
    }

    public function holdRelease(Request $request, Quest $quest): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'hold_until' => ['nullable', 'date'],
            'indefinite' => ['sometimes', 'boolean'],
        ]);

        $quest->update([
            'release_hold_reason' => $data['reason'],
            'release_hold_by' => $request->user()->id,
            'release_hold_until' => ($data['indefinite'] ?? false) ? null : ($data['hold_until'] ?? now()->addDays(7)),
        ]);

        $this->events->record($quest->fresh(), 'release_hold', $request->user(), $request, [
            'hold_until' => $quest->release_hold_until?->toIso8601String(),
            'indefinite' => (bool) ($data['indefinite'] ?? false),
        ]);

        return response()->json(['ok' => true]);
    }

    public function forceApproveAndRelease(Request $request, Quest $quest): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'confirm' => ['accepted'],
        ]);

        $lifecycle = app(\App\Services\Quest\QuestDeliveryLifecycleService::class);

        if ($quest->delivered_at === null && $quest->freelancer_id !== null) {
            $freelancer = $quest->freelancer ?? \App\Models\User::query()->find($quest->freelancer_id);
            if ($freelancer !== null) {
                $lifecycle->submitDeliverable($quest, $freelancer, [
                    'summary' => __('Platform staff recorded delivery on behalf of parties. Reason: :reason', ['reason' => $data['reason']]),
                    'delivery_url' => null,
                ]);
                $quest->refresh();
            }
        }

        if ($quest->delivery_acknowledged_at === null) {
            $quest->update([
                'delivery_acknowledged_at' => now(),
                'delivery_acknowledged_by' => $request->user()->id,
            ]);
            $this->events->record($quest->fresh(), 'sa_delivery_approved', $request->user(), $request, [
                'reason' => $data['reason'],
            ]);
            app(\App\Services\Moderation\ModerationDetectionHookService::class)->deliveryApproved($quest);
            $quest->refresh();
        }

        if ($quest->status === \App\Enums\QuestStatus::InProgress && in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            try {
                $this->escrow->releaseEscrowToWallet($quest, $request->user(), __('Super admin approved and released: :reason', ['reason' => $data['reason']]));
            } catch (\Illuminate\Validation\ValidationException $e) {
                return response()->json(['ok' => false, 'errors' => $e->errors()], 422);
            }

            $quest->refresh();
            $quest->update([
                'status' => \App\Enums\QuestStatus::Completed,
                'completed_at' => now(),
                'funds_released_at' => now(),
                'closure_type' => 'sa_force_approved_released',
            ]);

            $contract = \App\Models\QuestContract::query()->where('quest_id', $quest->id)->first();
            if ($contract !== null) {
                app(\App\Services\Contracts\ContractLifecycleService::class)->markCompleted($contract, $request->user(), $request);
            }

            $this->events->record($quest->fresh(), 'sa_funds_released', $request->user(), $request, [
                'reason' => $data['reason'],
            ]);
        }

        return response()->json(['ok' => true, 'quest_id' => $quest->id]);
    }

    public function liftHold(Request $request, Quest $quest): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $quest->update([
            'release_hold_reason' => null,
            'release_hold_by' => null,
            'release_hold_until' => null,
        ]);

        $this->events->record($quest->fresh(), 'release_hold_lifted', $request->user(), $request, [
            'reason' => $data['reason'],
        ]);

        return response()->json(['ok' => true]);
    }
}
