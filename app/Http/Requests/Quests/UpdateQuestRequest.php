<?php

namespace App\Http\Requests\Quests;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestPromotionTier;
use App\Enums\QuestStartTiming;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest && $this->user()?->can('update', $quest);
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $quest = $this->route('quest');
            $timing = $this->input('start_timing', $quest?->start_timing?->value);
            if ($timing === QuestStartTiming::Scheduled->value && ! $this->filled('scheduled_start_date')) {
                $v->errors()->add(
                    'scheduled_start_date',
                    __('Pick a start date when using a scheduled start.')
                );
            }
        });
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        $quest = $this->route('quest');

        return [
            'title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'string', 'max:50000'],
            'quest_category_id' => ['sometimes', 'integer', Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true)],
            'state_id' => ['sometimes', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'sometimes',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) $this->input('state_id', $quest?->state_id ?? 0)),
            ],
            'city' => ['sometimes', 'string', 'max:160'],
            'budget_amount_minor' => ['sometimes', 'integer', 'min:10000', 'max:100000000'],
            'start_timing' => ['sometimes', Rule::enum(QuestStartTiming::class)],
            'scheduled_start_date' => ['nullable', 'date'],
            'estimated_completion_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'estimated_delivery_date' => ['nullable', 'date'],
            'site_visits_allowed' => ['sometimes', 'boolean'],
            'visibility' => ['sometimes', Rule::enum(QuestVisibility::class)],
            'freelancer_location_pref' => ['sometimes', Rule::enum(QuestFreelancerLocationPref::class)],
            'availability_need' => ['nullable', Rule::enum(QuestAvailabilityNeed::class)],
            'project_type' => ['nullable', Rule::enum(QuestProjectType::class)],
            'estimated_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'team_size' => ['nullable', Rule::enum(QuestTeamSize::class)],
            'promotion_tier' => ['sometimes', Rule::enum(QuestPromotionTier::class)],
            'auto_listing_expiry_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:200'],
            'slug' => [
                'nullable',
                'string',
                'max:120',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('quests', 'slug')->ignore($quest?->id),
            ],
            'traffic_source' => ['nullable', 'string', 'max:128'],
            'traffic_utm' => ['nullable', 'array'],
            'traffic_utm.utm_source' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_medium' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_campaign' => ['nullable', 'string', 'max:64'],
        ];
    }
}
