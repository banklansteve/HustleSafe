<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitSupportFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reactionKeys = collect(config('customer_support.closure_reactions', []))
            ->pluck('key')
            ->filter()
            ->all();

        $answerKeys = collect(config('customer_support.feedback_survey', []))
            ->pluck('id')
            ->filter()
            ->all();

        return [
            'score' => ['required', 'integer', 'min:0', 'max:10'],
            'reaction' => ['nullable', 'string', Rule::in($reactionKeys)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'answers' => ['nullable', 'array'],
        ];
    }

    /**
     * @return list<string>
     */
    private function allowedAnswerValues(): array
    {
        $values = [];
        foreach (config('customer_support.feedback_survey', []) as $step) {
            foreach ($step['options'] ?? [] as $option) {
                if (! empty($option['value'])) {
                    $values[] = (string) $option['value'];
                }
            }
        }

        return $values;
    }

    /**
     * @return array<string, mixed>
     */
    public function validatedFeedback(): array
    {
        $validated = $this->validated();
        $answers = [];
        foreach (config('customer_support.feedback_survey', []) as $step) {
            $id = $step['id'] ?? null;
            if ($id && isset($validated['answers'][$id])) {
                $answers[$id] = $validated['answers'][$id];
            }
        }

        return [
            'score' => (int) $validated['score'],
            'reaction' => $validated['reaction'] ?? null,
            'comment' => $validated['comment'] ?? null,
            'answers' => $answers !== [] ? $answers : null,
        ];
    }
}
