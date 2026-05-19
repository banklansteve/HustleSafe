<?php

namespace App\Http\Requests\Quests;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestStartTiming;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\User;
use App\Services\QuestDescriptionSanitizer;
use App\Services\QuestFormFieldProfileService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quest::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('description')) {
            $this->merge([
                'description' => app(QuestDescriptionSanitizer::class)->clean((string) $this->input('description')),
            ]);
        }
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:50000'],
            'quest_category_id' => ['required', 'integer', Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true)->where('status', 'active')],
            'visibility' => ['required', Rule::enum(QuestVisibility::class)],
            'freelancer_location_pref' => ['required', Rule::enum(QuestFreelancerLocationPref::class)],
            'availability_need' => ['nullable', Rule::enum(QuestAvailabilityNeed::class)],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'required',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) $this->input('state_id', 0)),
            ],
            'city' => ['required', 'string', 'max:160'],
            'budget_amount_minor' => ['required', 'integer', 'min:10000', 'max:100000000'],
            'start_timing' => ['required', Rule::enum(QuestStartTiming::class)],
            'scheduled_start_date' => ['nullable', 'date'],
            'estimated_completion_days' => ['required', 'integer', 'min:1', 'max:365'],
            'estimated_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'site_visits_allowed' => ['sometimes', 'boolean'],
            'site_access_level' => ['nullable', 'string', Rule::in(['ground_level_easy', 'stairs_no_lift', 'stairs_with_lift', 'ladder_or_height_work', 'narrow_or_difficult_access', 'other'])],
            'pets_on_site' => ['sometimes', 'boolean'],
            'pets_detail' => ['nullable', 'string', 'max:255'],
            'project_type' => ['nullable', Rule::enum(QuestProjectType::class)],
            'estimated_hours' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'team_size' => ['nullable', Rule::enum(QuestTeamSize::class)],
            'auto_listing_expiry_days' => ['nullable', 'integer', 'min:1', 'max:90'],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:200'],
            'traffic_source' => ['nullable', 'string', 'max:128'],
            'traffic_utm' => ['nullable', 'array'],
            'traffic_utm.utm_source' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_medium' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_campaign' => ['nullable', 'string', 'max:64'],
            'publish_now' => ['sometimes', 'boolean'],
            'tagged_freelancer_ids' => ['nullable', 'array', 'max:20'],
            'tagged_freelancer_ids.*' => ['integer', 'distinct', Rule::exists('users', 'id')],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,gif,pdf'],
            'accepted_terms' => ['accepted'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v): void {
            $plainDesc = trim(html_entity_decode(strip_tags((string) $this->input('description', ''))));
            if ($plainDesc === '') {
                $v->errors()->add('description', __('Please add a written description for your quest.'));
            }

            $profiles = app(QuestFormFieldProfileService::class);
            $profile = $profiles->profileForLeafCategoryId((int) $this->input('quest_category_id', 0));
            $category = QuestCategory::query()->with('parent')->find((int) $this->input('quest_category_id', 0));
            $budget = (int) $this->input('budget_amount_minor', 0);
            $guardrail = $category?->budget_guardrails_enabled ? $category : ($category?->parent?->budget_guardrails_enabled ? $category->parent : null);
            if ($guardrail && (($guardrail->min_budget_minor && $budget < $guardrail->min_budget_minor) || ($guardrail->max_budget_minor && $budget > $guardrail->max_budget_minor))) {
                $v->errors()->add('budget_amount_minor', __('Typical budgets for :category are between :min and :max. Adjust the budget or choose a more suitable subcategory.', [
                    'category' => $category?->name,
                    'min' => '₦'.number_format(((int) $guardrail->min_budget_minor) / 100, 0),
                    'max' => '₦'.number_format(((int) $guardrail->max_budget_minor) / 100, 0),
                ]));
            }

            if ($this->input('start_timing') === QuestStartTiming::Scheduled->value && ! $this->filled('scheduled_start_date')) {
                $v->errors()->add('scheduled_start_date', __('Pick a start date when using a scheduled start.'));
            }

            $finish = $this->input('estimated_delivery_date');
            $sched = $this->input('scheduled_start_date');
            if (
                $finish
                && $this->input('start_timing') === QuestStartTiming::Scheduled->value
                && $sched
                && strcmp((string) $finish, (string) $sched) < 0
            ) {
                $v->errors()->add(
                    'estimated_delivery_date',
                    __('Planned finish must be on or after the planned start date.')
                );
            }

            if (! empty($profile['show_availability']) && ! $this->filled('availability_need')) {
                $v->errors()->add('availability_need', __('Select how you expect availability to look for this role.'));
            }

            if (! empty($profile['show_site_visit']) && ! $this->has('site_visits_allowed')) {
                $v->errors()->add('site_visits_allowed', __('Let freelancers know whether site visits are part of this brief.'));
            }

            if (! empty($profile['show_hourly_fields'])
                && $this->input('project_type') === QuestProjectType::Hourly->value
                && ! $this->filled('estimated_hours')) {
                $v->errors()->add('estimated_hours', __('Estimated hours help hourly listings stay realistic.'));
            }

            if (! empty($profile['show_team_size']) && ! $this->filled('team_size')) {
                $v->errors()->add('team_size', __('Choose whether you need one freelancer or a small squad.'));
            }

            if (! empty($profile['show_site_access'])) {
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

            if ($this->input('visibility') === QuestVisibility::InviteOnly->value
                && (! is_array($this->input('tagged_freelancer_ids')) || count($this->input('tagged_freelancer_ids')) < 1)) {
                $v->errors()->add('tagged_freelancer_ids', __('Invite-only quests need at least one tagged freelancer.'));
            }

            $ids = $this->input('tagged_freelancer_ids', []);
            if (! is_array($ids) || $ids === []) {
                return;
            }
            $clientId = $this->user()?->id;
            foreach ($ids as $id) {
                if ((int) $id === (int) $clientId) {
                    $v->errors()->add('tagged_freelancer_ids', __('You cannot tag yourself.'));

                    return;
                }
            }
            $bad = User::query()
                ->whereIn('id', $ids)
                ->whereRelation('role', 'slug', '<>', 'freelancer')
                ->exists();
            if ($bad) {
                $v->errors()->add('tagged_freelancer_ids', __('You can only tag freelancer accounts.'));
            }
        });
    }
}
