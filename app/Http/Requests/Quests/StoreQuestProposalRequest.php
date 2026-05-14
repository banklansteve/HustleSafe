<?php

namespace App\Http\Requests\Quests;

use App\Models\QuestOffer;
use App\Rules\NoDirectContactInformation;
use App\Support\ProposalMoneyCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreQuestProposalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'freelancer';
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

        $pricing = $this->input('pricing');
        if (is_array($pricing)) {
            if (! array_key_exists('withholding_tax_percent', $pricing)) {
                $pricing['withholding_tax_percent'] = 0;
            }
            if (! array_key_exists('vat_applies', $pricing)) {
                $pricing['vat_applies'] = true;
            }
            $this->merge(['pricing' => $pricing]);
        }

        $materials = $this->input('materials');
        if (is_array($materials)) {
            $out = [];
            foreach ($materials as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $qtyRaw = $row['quantity'] ?? '1';
                $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : (float) str_replace(',', '.', preg_replace('/[^0-9.,\-]/', '', (string) $qtyRaw));
                if (! is_finite($qty) || $qty < 0) {
                    $qty = 0.0;
                }
                if (array_key_exists('unit_price_ngn', $row)) {
                    $unit = max(0, (int) $row['unit_price_ngn']);
                    $row['cost_ngn'] = (int) round($qty * $unit);
                } elseif (! array_key_exists('cost_ngn', $row)) {
                    $row['cost_ngn'] = 0;
                } else {
                    $row['cost_ngn'] = max(0, (int) $row['cost_ngn']);
                }
                $out[] = $row;
            }
            $this->merge(['materials' => $out]);
        }
    }

    /**
     * @return array<string, array<int, mixed|string>>
     */
    public function rules(): array
    {
        $contact = [new NoDirectContactInformation];

        return [
            'pitch' => ['required', 'string', 'min:40', 'max:6000', ...$contact],
            'scope_detail' => ['required', 'string', 'min:80', 'max:20000', ...$contact],
            'warranty_terms' => ['nullable', 'string', 'max:2000', ...$contact],
            'planned_start_date' => ['required', 'date'],
            'planned_finish_date' => ['required', 'date', 'after_or_equal:planned_start_date'],
            'estimated_duration_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'corrections_included' => ['sometimes', 'boolean'],
            'corrections_rounds' => ['nullable', 'integer', 'min:1', 'max:50', 'required_if:corrections_included,true'],
            'progress_report_frequency' => ['nullable', 'string', Rule::in(['daily', 'twice_weekly', 'weekly', 'biweekly', 'milestone_based', 'on_request'])],
            'materials' => ['required', 'array', 'min:1', 'max:40'],
            'materials.*.label' => ['required', 'string', 'max:200'],
            'materials.*.quantity' => ['nullable', 'string', 'max:64'],
            'materials.*.unit_price_ngn' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'materials.*.cost_ngn' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'pricing' => ['required', 'array'],
            'pricing.professional_fee_ngn' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'pricing.vat_applies' => ['sometimes', 'boolean'],
            'pricing.withholding_tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pricing.travel_cost_ngn' => ['nullable', 'integer', 'min:0', 'max:500000000'],
            'pricing.stamp_duty_ngn' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'pricing.platform_fee_ngn' => ['nullable', 'integer', 'min:0', 'max:500000000'],
            'pricing.discount_ngn' => ['nullable', 'integer', 'min:0', 'max:500000000'],
            'pricing.grand_total_ngn' => ['required', 'integer', 'min:1', 'max:1000000000'],
            'accepted_terms' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $user = $this->user();
            $quest = $this->route('quest');
            if ($user === null || $quest === null) {
                return;
            }

            $exists = QuestOffer::query()
                ->where('quest_id', $quest->id)
                ->where('freelancer_id', $user->id)
                ->whereIn('status', ['submitted', 'shortlisted', 'accepted'])
                ->exists();

            if ($exists) {
                $v->errors()->add('proposal', __('You have already sent a proposal for this quest.'));
            }
        });

        $validator->after(function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
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

            $declaredNgn = max(0, (int) ($p['grand_total_ngn'] ?? 0));
            $expectedNgn = (int) round($breakdown['grand_minor'] / 100);

            if ($expectedNgn < 1) {
                $v->errors()->add('pricing.grand_total_ngn', __('Grand total must reflect your fees, materials, taxes, and discounts.'));

                return;
            }

            if ($declaredNgn !== $expectedNgn) {
                $v->errors()->add('pricing.grand_total_ngn', __('Totals do not add up. Check professional fee, materials, travel, taxes, fees, and discount.'));
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
            'materials' => $data['materials'],
            'pricing' => $data['pricing'],
        ], false);
    }
}
