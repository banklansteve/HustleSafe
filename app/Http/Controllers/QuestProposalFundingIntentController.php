<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\Payments\EscrowPaymentService;
use App\Services\Payments\PaystackClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestProposalFundingIntentController extends Controller
{
    public function __construct(
        private readonly EscrowPaymentService $escrowPayments,
        private readonly PaystackClient $paystack,
    ) {}

    public function store(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);

        $checkout = $this->escrowPayments->beginQuestFunding($quest, $offer, $request->user());

        if (! empty($checkout['authorization_url'])) {
            return redirect()->away($checkout['authorization_url']);
        }

        if (! empty($checkout['stub_mode'])) {
            return back()->with(
                'info',
                __('Paystack is not configured. Enable PAYSTACK_ENABLED and add sandbox keys, or use manual escrow confirmation for local testing.'),
            );
        }

        return back()->with('error', __('Could not start Paystack checkout.'));
    }
}
