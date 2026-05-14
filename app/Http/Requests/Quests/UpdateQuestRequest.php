<?php

namespace App\Http\Requests\Quests;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestPromotionTier;
use App\Enums\QuestStartTiming;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Services\QuestDescriptionSanitizer;
use App\Services\QuestFormFieldProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quest = $this->route('quest');

        return $quest && $this->user()?->can('update', $quest);
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            $this->merge([
                'description' => app(QuestDescriptionSanitizer::class)->clean((string) $this->input('description')),
            ]);
        }
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

            $finish = $this->input('estimated_delivery_date');
            $sched = $this->filled('scheduled_start_date')
                ? $this->input('scheduled_start_date')
                : ($quest?->scheduled_start_date?->format('Y-m-d'));
            if (
                $finish
                && $timing === QuestStartTiming::Scheduled->value
                && $sched
                && strcmp((string) $finish, (string) $sched) < 0
            ) {
                $v->errors()->add(
                    'estimated_delivery_date',
                    __('Planned finish must be on or after the planned start date.')
                );
            }

            if ($this->has('description')) {
                $plain = trim(html_entity_decode(strip_tags((string) $this->input('description', ''))));
                if ($plain === '') {
                    $v->errors()->add('description', __('Description cannot be empty.'));
                }
            }

            $catId = (int) ($this->input('quest_category_id', $quest?->quest_category_id ?? 0));
            $profile = app(QuestFormFieldProfileService::class)->profileForLeafCategoryId($catId > 0 ? $catId : null);

            if (! empty($profile['show_site_access'])
                && ($this->has('site_access_level') || $this->has('pets_on_site') || $this->has('pets_detail'))) {
                if (! $this->filled('site_access_level')) {
                    $v->errors()->add('site_access_level', __('Choose how accessible the location is for whoever will work on-site.'));
                }
                if (! $this->has('pets_on_site')) {
                    $v->errors()->add('pets_on_site', __('Let freelancers know whether pets are usually present at the location.'));
                }
            }

            if ($this->boolean('pets_on_site') && strlen(trim((string) $this->input('pets_detail', ''))) > 255) {
                $v->errors()->add('pets_detail', __('Keep pet notes under 255 characters.'));
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
            'site_access_level' => ['nullable', 'string', Rule::in(['ground_level_easy', 'stairs_no_lift', 'stairs_with_lift', 'ladder_or_height_work', 'narrow_or_difficult_access', 'other'])],
            'pets_on_site' => ['sometimes', 'boolean'],
            'pets_detail' => ['nullable', 'string', 'max:255'],
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
