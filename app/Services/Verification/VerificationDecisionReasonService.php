<?php

namespace App\Services\Verification;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class VerificationDecisionReasonService
{
    /**
     * @return list<array{value: string, label: string, hint: string}>
     */
    public function options(): array
    {
        return collect($this->definitions())
            ->map(fn (array $meta, string $code) => [
                'value' => $code,
                'label' => (string) $meta['label'],
                'hint' => (string) ($meta['hint'] ?? ''),
            ])
            ->values()
            ->all();
    }

    public function label(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        return Arr::get($this->definitions(), "{$code}.label");
    }

    /**
     * @return array{code: string, note: string, display: string, label: string}
     */
    public function resolve(?string $code, ?string $note, bool $required): array
    {
        $code = (string) ($code ?? '');
        $note = trim((string) ($note ?? ''));

        if (! $required) {
            return [
                'code' => $code !== '' ? $code : '',
                'note' => $note,
                'label' => $note !== '' ? __('Note') : '',
                'display' => $note,
            ];
        }

        if ($code === '' || ! array_key_exists($code, $this->definitions())) {
            throw ValidationException::withMessages([
                'reason_code' => __('Select a reason for this decision.'),
            ]);
        }

        $label = (string) $this->label($code);

        if ($code === 'other' && strlen($note) < 8) {
            throw ValidationException::withMessages([
                'reason_note' => __('Please add at least 8 characters explaining this decision.'),
            ]);
        }

        $display = $label;
        if ($note !== '') {
            $display .= '. '.$note;
        }

        return [
            'code' => $code,
            'note' => $note,
            'label' => $label,
            'display' => $display,
        ];
    }

    /**
     * @return array<string, array{label: string, hint?: string}>
     */
    private function definitions(): array
    {
        return config('verification_decision_reasons', []);
    }
}
