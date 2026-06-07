<?php

namespace App\Http\Requests\Quests;

use App\Models\QuestOffer;
use App\Rules\NoDirectContactInformation;
use App\Services\Verification\VerificationEngineService;
use App\Support\ProposalMoneyCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

class UpdateQuestProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $offer = $this->route('offer');

        if ($user === null || $offer === null || $user->role?->slug !== 'freelancer') {
            return false;
        }

        return (int) $offer->freelancer_id === (int) $user->id;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'corrections_included' => $this->boolean('corrections_included'),
        ]);
        if (! $this->boolean('corrections_included')) {
            $this->merge(['corrections_rounds' => null]);
        }
        if ($this->input('estimated_duration_days') === '' || $this->input('estimated_duration_days') === null) {
            $this->merge(['estimated_duration_days' => null]);
        }
        if ($this->input('progress_report_frequency') === '') {
            $this->merge(['progress_report_frequency' => null]);
        }
        if ($this->input('progress_report_frequency') !== 'custom') {
            $this->merge(['progress_report_frequency_note' => null]);
        }

        $pricing = $this->input('pricing');
        if (is_array($pricing)) {
            // Freelancer proposals are a clean quote (no platform/VAT/WHT lines).
            // Any platform + statutory lines are handled on the client funding flow.
            $pricing['vat_applies'] = false;
            $pricing['withholding_tax_percent'] = 0;
            $pricing['stamp_duty_ngn'] = 0;
            $pricing['platform_fee_ngn'] = 0;
            $this->merge(['pricing' => $pricing]);
        }

        $materials = ProposalMoneyCalculator::incomingMaterialRows($this->input('materials'));
        $this->merge(['materials' => $materials]);

        $pricing = $this->input('pricing', []);
        if (is_array($pricing)) {
            $pricing['vat_applies'] = false;
            $pricing['withholding_tax_percent'] = 0;
            $pricing['stamp_duty_ngn'] = 0;
            $pricing['platform_fee_ngn'] = 0;
            $breakdown = ProposalMoneyCalculator::breakdown($materials, $pricing);
            if ($breakdown !== null) {
                $pricing['grand_total_ngn'] = (int) round($breakdown['grand_minor'] / 100);
                $this->merge(['pricing' => $pricing]);
            }
        }
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        $contact = [new NoDirectContactInformation];
        $engine = app(VerificationEngineService::class);
        $maxNgn = $engine->platformMaxProposalValueNgn();

        return [
            'pitch' => ['required', 'string', 'min:40', 'max:6000', ...$contact],
            'scope_detail' => ['required', 'string', 'min:80', 'max:20000', ...$contact],
            'warranty_terms' => ['nullable', 'string', 'max:2000', ...$contact],
            'planned_start_date' => ['required', 'date'],
            'planned_finish_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'corrections_included' => ['sometimes', 'boolean'],
            'corrections_rounds' => ['nullable', 'integer', 'min:1', 'max:50', 'required_if:corrections_included,true'],
            'progress_report_frequency' => ['nullable', 'string', Rule::in(['daily', 'twice_weekly', 'weekly', 'biweekly', 'milestone_based', 'on_request', 'custom'])],
            'progress_report_frequency_note' => ['nullable', 'string', 'max:200', 'required_if:progress_report_frequency,custom'],
            'materials' => ['nullable', 'array', 'max:40'],
            'materials.*.label' => ['required', 'string', 'max:200'],
            'materials.*.quantity' => ['nullable', 'string', 'max:64'],
            'materials.*.unit_price_ngn' => ['nullable', 'integer', 'min:0', 'max:'.$maxNgn],
            'materials.*.cost_ngn' => ['nullable', 'integer', 'min:0', 'max:'.$maxNgn],
            'pricing' => ['required', 'array'],
            'pricing.professional_fee_ngn' => ['required', 'integer', 'min:0', 'max:'.$maxNgn],
            'pricing.travel_cost_ngn' => ['nullable', 'integer', 'min:0', 'max:'.$maxNgn],
            'pricing.discount_ngn' => ['nullable', 'integer', 'min:0', 'max:'.$maxNgn],
            'pricing.grand_total_ngn' => ['required', 'integer', 'min:1', 'max:'.$maxNgn],
            'confirm_revision' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $offer = $this->route('offer');
            if (! $offer instanceof QuestOffer) {
                return;
            }

            if (! in_array($offer->status, ['submitted', 'shortlisted'], true)) {
                $v->errors()->add('proposal', __('This proposal can no longer be edited.'));

                return;
            }

            if ($offer->freelancer_edit_deadline_at !== null && now()->greaterThan($offer->freelancer_edit_deadline_at)) {
                $v->errors()->add('proposal', __('The edit window for this proposal has closed.'));

                return;
            }

            $materials = $this->input('materials', []);
            if (! is_array($materials)) {
                return;
            }

            $p = $this->input('pricing', []);
            if (! is_array($p)) {
                return;
            }

            $breakdown = ProposalMoneyCalculator::breakdown($materials, $p);
            if ($breakdown === null) {
                return;
            }

            $expectedNgn = (int) round($breakdown['grand_minor'] / 100);

            if ($expectedNgn < 1) {
                $v->errors()->add('pricing.grand_total_ngn', __('Grand total must reflect your quote, materials, travel, and discounts.'));
            }
        });

        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $user = $this->user();
            $offer = $this->route('offer');
            if ($user === null || ! $offer instanceof QuestOffer) {
                return;
            }

            $quest = $offer->quest;
            if ($quest === null) {
                return;
            }

            $breakdown = ProposalMoneyCalculator::breakdown(
                is_array($this->input('materials')) ? $this->input('materials') : [],
                is_array($this->input('pricing')) ? $this->input('pricing') : [],
            );
            if ($breakdown === null) {
                return;
            }

            $grandMinor = (int) ($breakdown['grand_minor'] ?? 0);
            $engine = app(VerificationEngineService::class);

            if ($grandMinor > $engine->platformMaxProposalValueMinor()) {
                $v->errors()->add('pricing.grand_total_ngn', __('Proposal total cannot exceed the platform maximum of :amount.', [
                    'amount' => $engine->formatMoneyMinor($engine->platformMaxProposalValueMinor()),
                ]));

                return;
            }

            try {
                $engine->assertFreelancerCanPropose($user, $quest, $grandMinor);
            } catch (ValidationException $exception) {
                foreach ($exception->errors() as $field => $messages) {
                    foreach ($messages as $message) {
                        $v->errors()->add($field, $message);
                    }
                }
            }
        });
    }

    /**
     * @return array{materials: list<array<string, mixed>>, pricing_snapshot: array<string, mixed>}
     */
    public function normalizedPayload(): array
    {
        $data = $this->validated();

        return ProposalMoneyCalculator::normalizedPayload([
            'materials' => $data['materials'] ?? [],
            'pricing' => $data['pricing'],
        ], true);
    }
}
