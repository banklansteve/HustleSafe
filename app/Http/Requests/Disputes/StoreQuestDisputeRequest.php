<?php

namespace App\Http\Requests\Disputes;

use App\Enums\QuestDisputeReason;
use App\Rules\WordCountBetween;
use App\Services\Disputes\DisputeIntakeFormService;
use App\Services\Disputes\QuestDisputeWorkflowService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestDisputeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $minWords = (int) config('disputes.intake.description_min_words', 150);
        $maxWords = (int) config('disputes.intake.description_max_words', 1000);
        $maxFiles = (int) config('disputes.intake.evidence_max_files', 10);
        $maxFileKb = (int) config('disputes.intake.evidence_max_file_kb', 51200);
        $maxExternalLinks = (int) config('disputes.intake.external_links_max', 10);

        $party = $this->party();
        $resolutionValues = collect(app(DisputeIntakeFormService::class)->resolutionOptions($party))
            ->pluck('value')
            ->all();

        return [
            'reason' => ['required', 'string', Rule::enum(QuestDisputeReason::class)],
            'opening_summary' => ['required', 'string', new WordCountBetween($minWords, $maxWords)],
            'structured_intake' => ['required', 'array'],
            'structured_intake.impact' => ['required', 'array'],
            'structured_intake.impact.financial_impact_minor' => ['nullable', 'integer', 'min:0'],
            'structured_intake.impact.timeline_delay_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'structured_intake.impact.reputation_impact' => ['nullable', 'boolean'],
            'structured_intake.impact.contract_value_at_risk_minor' => ['nullable', 'integer', 'min:0'],
            'structured_intake.resolution_requested' => ['required', 'string', Rule::in($resolutionValues)],
            'structured_intake.resolution_amount_minor' => ['nullable', 'integer', 'min:0'],
            'structured_intake.timeline' => ['required', 'array'],
            'structured_intake.timeline.contract_awarded' => ['nullable', 'date'],
            'structured_intake.timeline.work_started' => ['nullable', 'date'],
            'structured_intake.timeline.expected_delivery' => ['nullable', 'date'],
            'structured_intake.timeline.deliverable_submitted' => ['nullable', 'date'],
            'structured_intake.timeline.issue_first_noticed' => ['nullable', 'date'],
            'structured_intake.timeline.informal_resolution_attempted' => ['nullable', 'boolean'],
            'structured_intake.timeline.informal_resolution_date' => ['nullable', 'date'],
            'structured_intake.timeline.escalation_date' => ['nullable', 'date'],
            'structured_intake.affected_areas' => ['required', 'array', 'min:1'],
            'structured_intake.affected_areas.*' => ['string', Rule::in(collect(app(DisputeIntakeFormService::class)->affectedAreas())->pluck('value')->all())],
            'structured_intake.prior_attempts' => ['required', 'array', 'min:1'],
            'structured_intake.prior_attempts.*' => ['string', Rule::in(collect(app(DisputeIntakeFormService::class)->priorAttemptOptions())->pluck('value')->all())],
            'structured_intake.prior_attempt_details' => ['nullable', 'array'],
            'structured_intake.prior_attempt_details.last_message_attempt_date' => ['nullable', 'date'],
            'structured_intake.prior_attempt_details.revision_request_count' => ['nullable', 'integer', 'min:0', 'max:100'],
            'structured_intake.prior_attempt_details.other_description' => ['nullable', 'string', 'max:2000'],
            'structured_intake.preferred_process' => ['required', 'string', Rule::in(['mediation', 'arbitration', 'full_investigation'])],
            'structured_intake.availability' => ['nullable', 'array'],
            'structured_intake.availability.*' => ['string', Rule::in(collect(app(DisputeIntakeFormService::class)->availabilityOptions())->pluck('value')->all())],
            'structured_intake.contact_method' => ['nullable', 'string', Rule::in(['email', 'platform', 'phone'])],
            'structured_intake.conversation_links' => ['nullable', 'array', 'max:25'],
            'structured_intake.conversation_links.*' => ['integer'],
            'structured_intake.external_links' => ['nullable', 'array', 'max:'.$maxExternalLinks],
            'structured_intake.external_links.*.url' => ['required_with:structured_intake.external_links', 'url', 'max:2048'],
            'structured_intake.external_links.*.description' => ['nullable', 'string', 'max:500'],
            'structured_intake.silence_days_observed' => ['nullable', 'integer', 'min:0', 'max:365'],
            'structured_intake.acknowledgments' => ['required', 'array'],
            'structured_intake.acknowledgments.accurate_information' => ['accepted'],
            'structured_intake.acknowledgments.binding_resolution' => ['accepted'],
            'structured_intake.acknowledgments.accept_platform_decision' => ['accepted'],
            'structured_intake.acknowledgments.false_claims_consequence' => ['accepted'],
            'structured_intake.acknowledgments.evidence_attached' => ['accepted'],
            'evidence_files' => ['nullable', 'array', 'max:'.$maxFiles],
            'evidence_files.*' => ['file', 'max:'.$maxFileKb],
            'confirm_philosophy' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $reasonValue = $this->input('reason');
            if (! is_string($reasonValue)) {
                return;
            }

            $reason = QuestDisputeReason::tryFrom($reasonValue);
            if ($reason === null) {
                return;
            }

            $party = $this->party();
            if (! $reason->allowedForParty($party)) {
                $validator->errors()->add('reason', __('You cannot raise the dispute under this reason code.'));
            }

            if ($reason->requiresSilenceDays()) {
                $days = (int) $this->input('structured_intake.silence_days_observed', 0);
                $minDays = (int) config('disputes.silence_comms_min_days', 5);
                if ($days < $minDays) {
                    $validator->errors()->add(
                        'structured_intake.silence_days_observed',
                        __('Document at least :n days without meaningful replies.', ['n' => $minDays]),
                    );
                }
            }

            $resolution = (string) $this->input('structured_intake.resolution_requested', '');
            if (in_array($resolution, ['partial_refund', 'partial_payment'], true)) {
                $amount = $this->input('structured_intake.resolution_amount_minor');
                if ($amount === null || (int) $amount <= 0) {
                    $validator->errors()->add('structured_intake.resolution_amount_minor', __('Enter the requested amount.'));
                }
            }
        });
    }

    protected function party(): string
    {
        $quest = $this->route('quest');
        $user = $this->user();

        if ($quest === null || $user === null) {
            return 'client';
        }

        return app(QuestDisputeWorkflowService::class)->partyFor($user, $quest) ?? 'client';
    }
}
