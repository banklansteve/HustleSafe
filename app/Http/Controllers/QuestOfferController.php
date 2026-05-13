<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\StoreQuestOfferRequest;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Services\FreelancerWorkspaceReadinessService;
use Illuminate\Http\RedirectResponse;

class QuestOfferController extends Controller
{
    public function store(
        StoreQuestOfferRequest $request,
        Quest $quest,
        FreelancerWorkspaceReadinessService $readiness,
    ): RedirectResponse {
        $user = $request->user();
        $data = $request->validated();
        $quoted = isset($data['quoted_amount_minor']) ? (int) $data['quoted_amount_minor'] : null;
        $readiness->assertCanSubmitOffer($user, $quest, $quoted);

        QuestOffer::query()->create([
            'quest_id' => $quest->id,
            'freelancer_id' => $user->id,
            'pitch' => $data['pitch'],
            'quoted_amount_minor' => $quoted,
            'status' => 'submitted',
        ]);

        $quest->increment('offers_count');

        return back()->with('success', __('Your offer was submitted.'));
    }
}
