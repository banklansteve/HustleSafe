<?php

namespace App\Http\Requests\Quests;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreQuestConversationMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        $maxBody = (int) config('quests.thread_message_body_max_default', 2000);
        $quest = $this->route('quest');
        if ($quest instanceof Quest) {
            $freelancerId = $this->resolveFreelancerPartyUserId($quest);
            if ($freelancerId !== null && $this->hasAcceptedProposal($quest->id, $freelancerId)) {
                $maxBody = (int) config('quests.thread_message_body_max_after_accepted', 720);
            }
        }

        return [
            // Contact/payment policy is enforced asynchronously after send via conversation monitoring.
            'body' => ['required', 'string', 'min:1', 'max:'.$maxBody],
        ];
    }

    protected function resolveFreelancerPartyUserId(Quest $quest): ?int
    {
        $user = $this->user();
        if ($user === null) {
            return null;
        }

        if ($user->role?->slug === 'freelancer') {
            return (int) $user->id;
        }

        if ((int) $quest->client_id !== (int) $user->id) {
            return null;
        }

        $contact = $this->route('contact');

        return $contact instanceof User ? (int) $contact->id : null;
    }

    protected function hasAcceptedProposal(int $questId, int $freelancerUserId): bool
    {
        return QuestOffer::query()
            ->where('quest_id', $questId)
            ->where('freelancer_id', $freelancerUserId)
            ->where('status', 'accepted')
            ->exists();
    }
}
