<?php

namespace App\Http\Requests\Contracts;

use App\Enums\DeliveryExtensionReasonCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractDeliveryExtensionRequest extends FormRequest
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
        return [
            'proposed_delivery_date' => ['required', 'date', 'after:today'],
            'reason_category' => ['required', Rule::enum(DeliveryExtensionReasonCategory::class)],
            'explanation' => ['required', 'string', 'min:50', 'max:5000'],
            'include_progress' => ['boolean'],
            'progress_note' => ['nullable', 'string', 'max:5000', 'required_if:include_progress,1,true'],
            'progress_attachments' => ['nullable', 'array', 'max:5'],
            'progress_attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'scope_change_message_id' => [
                'nullable',
                'integer',
                'exists:quest_conversation_messages,id',
                Rule::requiredIf(fn () => $this->input('reason_category') === DeliveryExtensionReasonCategory::ClientRequestedChanges->value),
            ],
        ];
    }
}
