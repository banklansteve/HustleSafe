<?php

namespace App\Http\Requests\Verification;

use App\Enums\UserVerificationCategory;
use App\Services\Verification\UserVerificationCatalogService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserVerificationRequest extends FormRequest
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
        $cat = $this->input('category');
        $isFreelancer = app(UserVerificationCatalogService::class)->isFreelancer($this->user());

        $allowed = [
            UserVerificationCategory::Nin->value,
            UserVerificationCategory::Bvn->value,
            UserVerificationCategory::IdentityAddress->value,
        ];

        if ($isFreelancer) {
            $allowed = array_merge($allowed, [
                UserVerificationCategory::Cac->value,
                UserVerificationCategory::Tin->value,
                UserVerificationCategory::LivePresence->value,
                UserVerificationCategory::ProfessionalCertificate->value,
            ]);
        }

        $base = [
            'category' => ['required', Rule::in($allowed)],
        ];

        if ($cat === UserVerificationCategory::LivePresence->value) {
            return array_merge($base, [
                'live_photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            ]);
        }

        if (in_array($cat, [UserVerificationCategory::Nin->value, UserVerificationCategory::Bvn->value], true)) {
            return array_merge($base, [
                'identifier_number' => ['required', 'digits:11'],
            ]);
        }

        if ($cat === UserVerificationCategory::IdentityAddress->value) {
            return array_merge($base, [
                'id_type' => ['required', Rule::in(['passport', 'national_id', 'drivers_licence', 'voters_card'])],
                'identifier_number' => ['required', 'string', 'max:64'],
                'confirmed_address' => ['required', 'string', 'max:500'],
                'id_document' => ['required', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'],
                'address_documents' => ['required', 'array', 'min:1', 'max:8'],
                'address_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'],
                'additional_id_documents' => ['nullable', 'array', 'max:4'],
                'additional_id_documents.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'],
                'additional_id_labels' => ['nullable', 'array'],
                'additional_id_labels.*' => ['nullable', 'string', 'max:160'],
            ]);
        }

        if (in_array($cat, [UserVerificationCategory::Cac->value, UserVerificationCategory::Tin->value], true)) {
            $rules = array_merge($base, [
                'identifier_number' => ['required', 'string', 'max:80'],
                'registered_business_name' => ['nullable', 'string', 'max:255'],
            ]);

            if ($this->hasFile('document_files') || $this->file('document_files')) {
                $rules['document_files'] = ['array', 'max:4'];
                $rules['document_files.*'] = ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'];
                $rules['document_labels'] = ['required_with:document_files', 'array'];
                $rules['document_labels.*'] = ['required', 'string', 'max:160'];
            }

            return $rules;
        }

        if ($cat === UserVerificationCategory::ProfessionalCertificate->value) {
            return array_merge($base, [
                'professional_entries' => ['required', 'array', 'min:1', 'max:10'],
                'professional_entries.*.what_submitting' => ['required', 'string', 'max:255'],
                'professional_entries.*.credential_identification' => ['nullable', 'string', 'max:120'],
                'professional_entries.*.awarding_body' => ['required', 'string', 'max:255'],
                'professional_entries.*.year' => ['required', 'integer', 'min:1950', 'max:'.(int) date('Y')],
                'professional_entries.*.file' => ['nullable', 'file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'],
            ]);
        }

        return $base;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cat = $this->input('category');
            if (! in_array($cat, [UserVerificationCategory::Cac->value, UserVerificationCategory::Tin->value], true)) {
                return;
            }

            $labels = $this->input('document_labels', []);
            $rawFiles = $this->file('document_files');
            if ($rawFiles === null) {
                return;
            }

            $files = is_array($rawFiles) ? array_values(array_filter($rawFiles)) : [$rawFiles];
            if (count($files) !== count((array) $labels)) {
                $validator->errors()->add('document_files', __('Each uploaded file needs a matching document name.'));
            }
        });
    }
}
