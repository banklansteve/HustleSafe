<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role?->slug === 'super_admin';
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'min:4', 'max:32', 'alpha_dash', 'unique:promotion_coupons,code'],
            'discount_type' => ['required', Rule::in(['percent', 'fixed'])],
            'discount_percent' => ['required_if:discount_type,percent', 'nullable', 'integer', 'min:1', 'max:100'],
            'discount_value_minor' => ['required_if:discount_type,fixed', 'nullable', 'integer', 'min:1'],
            'max_discount_minor' => ['nullable', 'integer', 'min:0'],
            'applies_to' => ['required', Rule::in(['service_fee', 'featured_listing', 'all'])],
            'quest_category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'eligibility' => ['required', Rule::in(['all', 'new_users', 'clients', 'freelancers', 'specific_users'])],
            'eligible_user_ids' => ['nullable', 'array'],
            'eligible_user_ids.*' => ['integer', 'exists:users,id'],
            'usage_limit_total' => ['nullable', 'integer', 'min:1'],
            'usage_limit_per_user' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'minimum_transaction_minor' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
