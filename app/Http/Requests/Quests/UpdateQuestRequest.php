<?php

namespace App\Http\Requests\Quests;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestStartTiming;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Models\QuestCategory;
use App\Services\QuestDescriptionSanitizer;
use App\Services\QuestFormFieldProfileService;
use App\Support\PlatformSettings;
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
        $profiles = app(QuestFormFieldProfileService::class);
        $quest = $this->route('quest');
        $catId = (int) ($this->input('quest_category_id', $quest?->quest_category_id ?? 0));
        $profile = $profiles->profileForLeafCategoryId($catId > 0 ? $catId : null);

        $data = $this->all();

        if ($this->has('description')) {
            $data['description'] = app(QuestDescriptionSanitizer::class)->clean((string) $data['description']);
        }

        $this->replace($profiles->normalizeSubmittedPayload($data, $profile));
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
            $category = QuestCategory::query()->with('parent')->find($catId);
            $budget = (int) ($this->input('budget_amount_minor', $quest?->budget_amount_minor ?? 0));
            $guardrail = $category?->budget_guardrails_enabled ? $category : ($category?->parent?->budget_guardrails_enabled ? $category->parent : null);
            if ($guardrail && (($guardrail->min_budget_minor && $budget < $guardrail->min_budget_minor) || ($guardrail->max_budget_minor && $budget > $guardrail->max_budget_minor))) {
                $v->errors()->add('budget_amount_minor', __('Typical budgets for :category are between :min and :max.', [
                    'category' => $category?->name,
                    'min' => '₦'.number_format(((int) $guardrail->min_budget_minor) / 100, 0),
                    'max' => '₦'.number_format(((int) $guardrail->max_budget_minor) / 100, 0),
                ]));
            }

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
            'quest_category_id' => ['sometimes', 'integer', Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true)->where('status', 'active')],
            'state_id' => ['sometimes', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'sometimes',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) $this->input('state_id', $quest?->state_id ?? 0)),
            ],
            'city' => ['sometimes', 'string', 'max:160'],
            'budget_amount_minor' => ['sometimes', 'integer', 'min:10000', 'max:500000000'],
            'start_timing' => ['sometimes', Rule::enum(QuestStartTiming::class)],
            'scheduled_start_date' => ['nullable', 'date'],
            'estimated_completion_days' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'estimated_delivery_date' => ['nullable', 'date'],
            'visibility' => ['sometimes', Rule::enum(QuestVisibility::class)],
            'freelancer_location_pref' => ['sometimes', Rule::enum(QuestFreelancerLocationPref::class)],
            'availability_need' => ['nullable', Rule::enum(QuestAvailabilityNeed::class)],
            'project_type' => ['nullable', Rule::enum(QuestProjectType::class)],
            'estimated_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'team_size' => ['nullable', Rule::enum(QuestTeamSize::class)],
            'auto_listing_expiry_days' => [
                'nullable',
                'integer',
                'min:'.PlatformSettings::proposalDeadlineBounds()['min'],
                'max:'.PlatformSettings::proposalDeadlineBounds()['max'],
            ],
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
