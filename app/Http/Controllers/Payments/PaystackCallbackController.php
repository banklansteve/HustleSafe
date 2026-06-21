<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\QuestOffer;
use App\Services\Payments\EscrowPaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaystackCallbackController extends Controller
{
    public function __construct(private readonly EscrowPaymentService $escrowPayments) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $reference = (string) $request->query('reference', '');
        if ($reference === '') {
            return redirect()->route('account.show')->with('error', __('Payment reference missing.'));
        }

        try {
            $escrow = $this->escrowPayments->verifyAndFund($reference);
            $quest = $escrow->quest;

            if ($quest !== null) {
                $offer = QuestOffer::query()->find($escrow->quest_offer_id);

                return redirect()
                    ->route('quests.proposals.show', [$quest->getRouteKey(), $offer ?? $escrow->quest_offer_id])
                    ->with('success', __('Payment confirmed. Escrow is funded — the freelancer can begin work.'))
                    ->with('show_escrow_funding_notice', true);
            }
        } catch (\Throwable $e) {
            report($e);

            return redirect()
                ->route('account.show')
                ->with('error', $e->getMessage() ?: __('We could not verify your payment yet. It may still be processing.'));
        }

        return redirect()->route('account.show')->with('success', __('Payment confirmed.'));
    }
}
