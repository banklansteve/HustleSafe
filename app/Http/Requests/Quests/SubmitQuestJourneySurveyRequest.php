<?php

namespace App\Http\Requests\Quests;

use App\Models\QuestJourneySurvey;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitQuestJourneySurveyRequest extends FormRequest
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
        return [
            'answers' => ['required', 'array'],
            'answers.*' => ['nullable'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $token = (string) $this->route('token');
            $survey = QuestJourneySurvey::query()->where('token', $token)->first();
            if (! $survey) {
                return;
            }

            $incoming = $this->input('answers', []);
            $survey->answers = array_merge($survey->answers ?? [], $incoming);
            $steps = app(QuestJourneySurveyService::class)->remainingSteps($survey);
            $answers = $incoming;

            foreach ($steps as $step) {
                $key = $step['key'];
                $value = $answers[$key] ?? null;
                $optional = (bool) ($step['optional'] ?? false);

                if (! $optional && ($value === null || $value === '')) {
                    $validator->errors()->add("answers.{$key}", __('This question is required.'));

                    continue;
                }

                if (($step['type'] ?? '') === 'text' && is_string($value)) {
                    $max = (int) ($step['max'] ?? 500);
                    if (strlen($value) > $max) {
                        $validator->errors()->add("answers.{$key}", __('Please keep your answer under :max characters.', ['max' => $max]));
                    }
                }

                if (($step['type'] ?? '') === 'nps' && $value !== null && $value !== '') {
                    if (! is_numeric($value) || (int) $value < 0 || (int) $value > 10) {
                        $validator->errors()->add("answers.{$key}", __('Please choose a score between 0 and 10.'));
                    }
                }
            }
        });
    }
}
