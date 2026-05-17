<?php

namespace App\Http\Controllers;

use App\Models\Quest;
use App\Models\QuestFundingIntent;
use App\Models\QuestOffer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class QuestProposalFundingIntentController extends Controller
{
    public function store(Request $request, Quest $quest, QuestOffer $offer): RedirectResponse
    {
        $this->authorize('respondAsClient', $offer);

        if ((int) $offer->quest_id !== (int) $quest->id) {
            abort(404);
        }

        if ((int) $quest->client_id !== (int) $request->user()?->id) {
            abort(403);
        }

        if ((int) ($quest->accepted_quest_offer_id ?? 0) !== (int) $offer->id || $offer->status !== 'accepted') {
            throw ValidationException::withMessages(['offer' => [__('Funding intents only apply to the accepted proposal.')]]);
        }

        if ($quest->escrow_status !== 'awaiting_funding') {
            throw ValidationException::withMessages(['offer' => [__('Escrow is not awaiting funding right now.')]]);
        }

        $p = $offer->pricing_snapshot ?? [];
        $quoted = (int) ($p['grand_total_minor'] ?? $offer->quoted_amount_minor ?? 0);

        QuestFundingIntent::query()->create([
            'quest_id' => $quest->id,
            'quest_offer_id' => $offer->id,
            'initiated_by_user_id' => $request->user()->id,
            'quoted_total_minor' => $quoted,
            'status' => 'initiated',
            'gateway_key' => (string) config('escrow.driver', 'stub'),
            'meta' => [
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
                'ip' => $request->ip(),
            ],
        ]);

        return back()->with(
            'success',
            __('Payment checkout is not wired yet — we logged your funding intent. You will return here after the gateway is connected.')
        );
    }
}
