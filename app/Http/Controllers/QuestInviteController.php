<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\SyncQuestInvitesRequest;
use App\Models\Quest;
use App\Models\User;
use App\Services\Quest\QuestListingExpiryService;
use App\Services\QuestPublishedNotificationService;
use Illuminate\Http\RedirectResponse;

class QuestInviteController extends Controller
{
    public function store(
        SyncQuestInvitesRequest $request,
        Quest $quest,
        QuestPublishedNotificationService $notifier,
    ): RedirectResponse {
        $ids = array_values(array_unique(array_map('intval', $request->validated()['freelancer_ids'])));
        $existing = $quest->invitedFreelancerIds();

        $quest->invitedFreelancers()->sync($ids);

        $newIds = array_values(array_diff($ids, $existing));
        if ($newIds !== [] && app(QuestListingExpiryService::class)->acceptsFreelancerInvites($quest)) {
            $notifier->notifyTagged($quest, $newIds);
        }

        return back()->with('success', __('Invite sent. They’ll receive an email and in-app notification with your quest link.'));
    }

    public function destroy(Quest $quest, User $freelancer): RedirectResponse
    {
        $this->authorize('manageInvites', $quest);

        if ($freelancer->role?->slug !== 'freelancer') {
            abort(404);
        }

        $quest->invitedFreelancers()->detach($freelancer->id);

        return back()->with('success', __('Invite removed.'));
    }
}
