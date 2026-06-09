<?php

namespace App\Http\Requests\Quests;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\User;
use App\Services\Quest\QuestListingExpiryService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncQuestInvitesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest !== null && $this->user()?->can('manageInvites', $quest);
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'freelancer_ids' => ['present', 'array', 'max:'.(int) config('quest_matching.quest_invite_freelancer_max', 100)],
            'freelancer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            /** @var Quest|null $quest */
            $quest = $this->route('quest');
            if ($quest === null) {
                return;
            }

            if (! app(QuestListingExpiryService::class)->acceptsFreelancerInvites($quest)) {
                $v->errors()->add('freelancer_ids', __('You can only tag freelancers while the quest is open, unassigned, and still accepting proposals.'));

                return;
            }

            $ids = $this->input('freelancer_ids', []);
            if (! is_array($ids)) {
                return;
            }

            $clientId = $this->user()?->id;
            foreach ($ids as $id) {
                if ((int) $id === (int) $clientId) {
                    $v->errors()->add('freelancer_ids', __('You cannot tag yourself.'));

                    return;
                }
            }

            if ($ids === []) {
                return;
            }

            $bad = User::query()
                ->whereIn('id', $ids)
                ->whereRelation('role', 'slug', '<>', 'freelancer')
                ->exists();
            if ($bad) {
                $v->errors()->add('freelancer_ids', __('You can only tag freelancer accounts.'));

                return;
            }

            $existing = $quest->invitedFreelancerIds();
            $newIds = array_values(array_diff(array_map('intval', $ids), $existing));

            if ($newIds === []) {
                return;
            }

            $alreadyProposed = QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->whereIn('freelancer_id', $newIds)
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->exists();

            if ($alreadyProposed) {
                $v->errors()->add('freelancer_ids', __('You can only tag freelancers who have not already proposed on this quest.'));
            }
        });
    }
}
