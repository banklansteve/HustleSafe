<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreRegisteredUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'account_type' => ['required', 'string', Rule::in(['sponsor', 'hustler'])],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'gender' => ['nullable', 'string', Rule::in(['female', 'male', 'non_binary', 'prefer_not_to_say'])],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:32', 'regex:/^[0-9+\-\s()]+$/'],
            'address_line' => ['required', 'string', 'max:500'],
            'city' => ['required', 'string', 'max:160'],
            'state_id' => ['required', 'integer', 'exists:states,id'],
            'local_government_id' => [
                'required',
                'integer',
                Rule::exists('local_governments', 'id')->where('state_id', (int) $this->input('state_id', 0)),
            ],
            'quest_category_ids' => [
                Rule::excludeUnless(fn () => $this->input('account_type') === 'hustler'),
                'required',
                'array',
                'min:1',
                'max:40',
            ],
            'quest_category_ids.*' => [
                Rule::excludeUnless(fn () => $this->input('account_type') === 'hustler'),
                'integer',
                'distinct',
                Rule::exists('quest_categories', 'id')->whereNotNull('parent_id')->where('is_active', true),
            ],
            'accepted_terms' => ['accepted'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'referral_code' => ['nullable', 'string', 'max:24'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'account_type' => __('account type'),
            'first_name' => __('first name'),
            'last_name' => __('last name'),
            'gender' => __('gender'),
            'date_of_birth' => __('date of birth'),
            'company_name' => __('company name'),
            'email' => __('email address'),
            'phone' => __('phone number'),
            'address_line' => __('address'),
            'city' => __('city'),
            'state_id' => __('state'),
            'local_government_id' => __('local government'),
            'password' => __('password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_type.required' => __('Choose how you will use HustleSafe.'),
            'account_type.in' => __('Choose how you will use HustleSafe.'),
            'first_name.required' => __('The first name field is required.'),
            'last_name.required' => __('The last name field is required.'),
            'email.required' => __('The email field is required.'),
            'email.email' => __('The email must be a valid email address.'),
            'email.unique' => __('This email is already registered.'),
            'phone.required' => __('The phone number field is required.'),
            'phone.regex' => __('Use a valid phone number.'),
            'address_line.required' => __('The address field is required.'),
            'state_id.required' => __('Please select a state.'),
            'state_id.exists' => __('Please select a valid state.'),
            'local_government_id.required' => __('Please select a local government area.'),
            'local_government_id.exists' => __('Please select a valid local government for that state.'),
            'city.required' => __('The city field is required.'),
            'quest_category_ids.required' => __('Pick at least one work category so we can match you to quests.'),
            'quest_category_ids.min' => __('Pick at least one work category so we can match you to quests.'),
            'password.required' => __('The password field is required.'),
            'password.confirmed' => __('The password confirmation does not match.'),
            'accepted_terms.accepted' => __('You must agree to the Terms of Service and Privacy Policy to create an account.'),
        ];
    }
}
