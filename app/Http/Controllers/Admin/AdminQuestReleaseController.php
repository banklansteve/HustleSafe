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
