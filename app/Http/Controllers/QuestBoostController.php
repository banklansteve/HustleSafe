<?php

namespace App\Http\Controllers;

use App\Enums\QuestBoostTier;
use App\Http\Requests\Quests\StoreQuestBoostCheckoutRequest;
use App\Models\Quest;
use App\Services\Quest\ClientQuestBoostService;
use App\Services\Quest\QuestBoostPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class QuestBoostController extends Controller
{
    public function __construct(
        private readonly ClientQuestBoostService $clientBoosts,
        private readonly QuestBoostPaymentService $payments,
    ) {}

    public function checkout(StoreQuestBoostCheckoutRequest $request, Quest $quest): RedirectResponse|Response
    {
        $tier = QuestBoostTier::from((string) $request->validated('tier'));

        $payload = $this->payments->initialize($quest, $request->user(), $tier);

        if ($payload['stub_mode'] ?? false) {
            $this->payments->verifyAndActivate($payload['reference']);
            $payment = \App\Models\QuestBoostPayment::query()
                ->where('paystack_reference', $payload['reference'])
                ->with('questBoost')
                ->first();

            return redirect()
                ->route('quests.show', $quest)
                ->with('success', $this->followOnSuccessMessage($payment))
                ->withFragment('boost-quest');
        }

        return \Inertia\Inertia::location($payload['authorization_url']);
    }

    public function dismissUpsell(Request $request, Quest $quest): RedirectResponse
    {
        $this->authorize('update', $quest);

        $this->clientBoosts->dismissUpsell($quest, $request->user());

        return back();
    }

    private function followOnSuccessMessage(?\App\Models\QuestBoostPayment $payment): string
    {
        $boost = $payment?->questBoost;
        if ($boost !== null && $boost->starts_at !== null && $boost->starts_at->isFuture()) {
            return __('Follow-on boost confirmed. Your current boost runs until :current, then your new boost runs until :extended.', [
                'current' => $boost->starts_at->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'extended' => $boost->ends_at?->timezone('Africa/Lagos')->format('j M Y, g:i A') ?? '—',
            ]);
        }

        return __('Payment confirmed — your quest is now boosted!');
    }

    public function callback(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');

        if ($reference === '') {
            return redirect()->route('quests.index')->with('error', __('Payment reference missing.'));
        }

        try {
            $payment = $this->payments->verifyAndActivate($reference);
            $quest = $payment->quest;
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('quests.index')->with('error', __('Payment could not be verified. Please contact support if you were charged.'));
        }

        return redirect()
            ->route('quests.show', $quest)
            ->with('success', $this->followOnSuccessMessage($payment->fresh(['questBoost'])))
            ->withFragment('boost-quest');
    }
}
