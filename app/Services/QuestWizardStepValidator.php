<?php

namespace App\Services;

use App\Enums\QuestAvailabilityNeed;
use App\Enums\QuestFreelancerLocationPref;
use App\Enums\QuestProjectType;
use App\Enums\QuestStartTiming;
use App\Enums\QuestTeamSize;
use App\Enums\QuestVisibility;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as IlluminateValidator;

class QuestWizardStepValidator
{
    public function __construct(
        protected QuestFormFieldProfileService $fieldProfiles,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function validate(int $step, array $payload): IlluminateValidator
    {
        $categoryId = isset($payload['quest_category_id']) ? (int) $payload['quest_category_id'] : null;
        $profile = $this->fieldProfiles->profileForLeafCategoryId($categoryId);

        return match ($step) {
            1 => Validator::make($payload, $this->step1Rules()),
            2 => Validator::make($payload, $this->step2Rules($profile)),
            3 => Validator::make($payload, $this->step3Rules($payload)),
            4 => Validator::make($payload, $this->step4Rules($payload)),
            5 => Validator::make($payload, $this->step5Rules($profile, $payload)),
            6 => Validator::make($payload, $this->step6Rules()),
            default => Validator::make(
                ['wizard_step' => $step],
                ['wizard_step' => ['required', 'in:1,2,3,4,5,6']]
            ),
        };
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    protected function step1Rules(): array
    {
        return [
            'quest_category_id' => ['required', 'integer', Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['required', 'string', 'max:50000'],
        ];
    }

    /**
     * @param  array<string, bool|string|null>  $profile
     * @return array<string, array<int, mixed|string>>
     */
    protected function step2Rules(array $profile): array
    {
        $rules = [
            'visibility' => ['required', Rule::enum(QuestVisibility::class)],
            'freelancer_location_pref' => ['required', Rule::enum(QuestFreelancerLocationPref::class)],
            'traffic_source' => ['nullable', 'string', 'max:128'],
            'traffic_utm' => ['nullable', 'array'],
            'traffic_utm.utm_source' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_medium' => ['nullable', 'string', 'max:64'],
            'traffic_utm.utm_campaign' => ['nullable', 'string', 'max:64'],
        ];

        if (! empty($profile['show_availability'])) {
            $rules['availability_need'] = ['required', Rule::enum(QuestAvailabilityNeed::class)];
        } else {
            $rules['availability_need'] = ['nullable', Rule::enum(QuestAvailabilityNeed::class)];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, mixed|string>>
     */
    protected function step3Rules(array $payload): array
    {
        return [
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'required',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) ($payload['state_id'] ?? 0)),
            ],
            'city' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, mixed|string>>
     */
    protected function step4Rules(array $payload): array
    {
        $timing = $payload['start_timing'] ?? null;

        return [
            'start_timing' => ['required', Rule::enum(QuestStartTiming::class)],
            'scheduled_start_date' => [
                'nullable',
                'date',
                Rule::requiredIf(fn () => $timing === QuestStartTiming::Scheduled->value),
            ],
            'estimated_completion_days' => ['required', 'integer', 'min:1', 'max:365'],
            'estimated_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'budget_amount_minor' => ['required', 'integer', 'min:10000', 'max:500000000'],
        ];
    }

    /**
     * @param  array<string, bool|string|null>  $profile
     * @param  array<string, mixed>  $payload
     * @return array<string, array<int, mixed|string>>
     */
    protected function step5Rules(array $profile, array $payload): array
    {
        $rules = [
            'project_type' => ['nullable', Rule::enum(QuestProjectType::class)],
            'team_size' => ['nullable', Rule::enum(QuestTeamSize::class)],
        ];

        if (! empty($profile['show_site_visit'])) {
            $rules['site_visits_allowed'] = ['required', 'boolean'];
        } else {
            $rules['site_visits_allowed'] = ['sometimes', 'boolean'];
        }

        $projectType = $payload['project_type'] ?? null;
        if (! empty($profile['show_hourly_fields']) && $projectType === QuestProjectType::Hourly->value) {
            $rules['estimated_hours'] = ['required', 'integer', 'min:1', 'max:2000'];
        } else {
            $rules['estimated_hours'] = ['nullable', 'integer', 'min:1', 'max:2000'];
        }

        if (! empty($profile['show_team_size'])) {
            $rules['team_size'] = ['required', Rule::enum(QuestTeamSize::class)];
        }

        if (! empty($profile['show_site_access'])) {
            $rules['site_access_level'] = ['required', 'string', Rule::in(['ground_level_easy', 'stairs_no_lift', 'stairs_with_lift', 'ladder_or_height_work', 'narrow_or_difficult_access', 'other'])];
            $rules['pets_on_site'] = ['required', 'boolean'];
            $rules['pets_detail'] = ['nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    protected function step6Rules(): array
    {
        $bounds = PlatformSettings::proposalDeadlineBounds();

        return [
            'auto_listing_expiry_days' => ['required', 'integer', 'min:'.$bounds['min'], 'max:'.$bounds['max']],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:200'],
            'tagged_freelancer_ids' => ['nullable', 'array', 'max:20'],
            'tagged_freelancer_ids.*' => ['integer', 'distinct'],
        ];
    }
}
