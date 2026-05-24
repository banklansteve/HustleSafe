<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

class SendCustomerSupportMessageRequest extends FormRequest
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
            'body' => ['nullable', 'string', 'max:8000'],
            'attachments' => ['nullable', 'array', 'max:'.(int) config('customer_support.max_attachments', 5)],
            'attachments.*' => ['file', 'max:'.(int) config('customer_support.max_attachment_kb', 10240), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx'],
            'gif_url' => ['nullable', 'url', 'max:2048'],
            'visibility' => ['nullable', 'string', 'in:public,internal'],
        ];
    }
}
