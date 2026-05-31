<?php

namespace App\Http\Controllers;

use App\Http\Requests\Quests\SubmitQuestJourneySurveyRequest;
use App\Models\QuestJourneySurvey;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestJourneySurveyController extends Controller
{
    public function __construct(private readonly QuestJourneySurveyService $surveys) {}

    public function capture(Request $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $survey = $this->findSurvey($token);

        if ($survey->isExpired()) {
            return redirect()->route('journey-survey.closed');
        }

        $answer = (string) $request->query('answer', '');
        if ($answer !== '') {
            $this->surveys->captureFirstAnswer($survey, $answer);
        }

        return redirect()->to($this->surveys->showUrl($survey->fresh()));
    }

    public function show(Request $request, string $token): Response|RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $survey = $this->findSurvey($token);

        if ($survey->isExpired() && ! $survey->isSubmitted()) {
            return Inertia::render('JourneySurvey/Closed');
        }

        $survey->loadMissing('quest:id,title');

        return Inertia::render('JourneySurvey/Form', [
            'survey' => [
                'token' => $survey->token,
                'cohort' => $survey->cohort,
                'quest_title' => $survey->quest?->title,
                'submitted' => $survey->isSubmitted(),
                'expired' => $survey->isExpired(),
                'first_answer_at' => $survey->first_answer_at?->toIso8601String(),
            ],
            'steps' => $this->surveys->remainingSteps($survey),
            'prefill' => $survey->answers ?? [],
            'submitUrl' => $this->surveys->submitUrl($survey),
        ]);
    }

    public function submit(SubmitQuestJourneySurveyRequest $request, string $token): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $survey = $this->findSurvey($token);

        if ($survey->isExpired() && ! $survey->isSubmitted()) {
            return redirect()->route('journey-survey.closed');
        }

        $this->surveys->submit($survey, $request->validated('answers'));

        return redirect()->to($this->surveys->showUrl($survey->fresh()));
    }

    public function closed(): Response
    {
        return Inertia::render('JourneySurvey/Closed');
    }

    private function findSurvey(string $token): QuestJourneySurvey
    {
        return QuestJourneySurvey::query()
            ->where('token', $token)
            ->firstOrFail();
    }
}
