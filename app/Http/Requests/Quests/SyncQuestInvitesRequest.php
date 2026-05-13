<?php

namespace App\Http\Requests\Quests;

use App\Models\User;
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
            'freelancer_ids' => ['present', 'array', 'max:20'],
            'freelancer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $ids = $this->input('freelancer_ids', []);
            if (! is_array($ids) || $ids === []) {
                return;
            }
            $clientId = $this->user()?->id;
            foreach ($ids as $id) {
                if ((int) $id === (int) $clientId) {
                    $v->errors()->add('freelancer_ids', __('You cannot tag yourself.'));

                    return;
                }
            }
            $bad = User::query()
                ->whereIn('id', $ids)
                ->whereRelation('role', 'slug', '<>', 'freelancer')
                ->exists();
            if ($bad) {
                $v->errors()->add('freelancer_ids', __('You can only tag freelancer accounts.'));
            }
        });
    }
}
