<?php

namespace App\Http\Requests\Quests;

use App\Support\PlatformSettings;
use Illuminate\Foundation\Http\FormRequest;

class ExtendQuestListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest !== null && $this->user()?->can('extendListing', $quest);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = PlatformSettings::proposalDeadlineBounds()['extension_max'];

        return [
            'additional_days' => ['required', 'integer', 'min:1', 'max:'.$max],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
