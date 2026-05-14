<?php

namespace App\Http\Requests\Verification;

use App\Enums\UserVerificationCategory;
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

        $base = [
            'category' => ['required', Rule::enum(UserVerificationCategory::class)],
            'freelancer_credential_id' => [
                Rule::requiredIf(fn () => $this->input('category') === UserVerificationCategory::Qualification->value),
                'nullable',
                'integer',
                Rule::exists('freelancer_credentials', 'id')->where('user_id', $this->user()->id),
            ],
        ];

        if ($cat === UserVerificationCategory::LivePresence->value) {
            return array_merge($base, [
                'live_photo' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:15360'],
            ]);
        }

        $doc = [
            'document_files' => ['required', 'array', 'min:1', 'max:8'],
            'document_files.*' => ['file', 'mimes:jpeg,jpg,png,webp,pdf', 'max:15360'],
            'document_labels' => ['required', 'array'],
            'document_labels.*' => ['required', 'string', 'max:160'],
        ];

        if ($cat === UserVerificationCategory::Identity->value) {
            return array_merge($base, $doc, [
                'id_type' => ['required', Rule::in(['nin', 'passport', 'drivers_licence'])],
                'identifier_number' => ['required', 'string', 'max:64'],
            ]);
        }

        if ($cat === UserVerificationCategory::Address->value || $cat === UserVerificationCategory::Qualification->value) {
            return array_merge($base, $doc);
        }

        return $base;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $cat = $this->input('category');
            if ($cat !== UserVerificationCategory::Qualification->value && $this->filled('freelancer_credential_id')) {
                $validator->errors()->add('freelancer_credential_id', __('Credential link is only used for qualification reviews.'));
            }

            if ($cat === UserVerificationCategory::LivePresence->value) {
                return;
            }

            if (! in_array($cat, [
                UserVerificationCategory::Identity->value,
                UserVerificationCategory::Address->value,
                UserVerificationCategory::Qualification->value,
            ], true)) {
                return;
            }

            $labels = $this->input('document_labels', []);
            if (! is_array($labels)) {
                $validator->errors()->add('document_labels', __('Invalid document labels.'));

                return;
            }

            $rawFiles = $this->file('document_files');
            $files = [];
            if (is_array($rawFiles)) {
                $files = array_values(array_filter($rawFiles));
            } elseif ($rawFiles !== null) {
                $files = [$rawFiles];
            }

            if (count($files) !== count($labels)) {
                $validator->errors()->add('document_files', __('Each uploaded file needs a matching document name.'));
            }
        });
    }
}
