<?php

namespace App\Jobs;

use App\Mail\QuestJourneySurveyMail;
use App\Models\QuestJourneySurvey;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendQuestJourneySurveyReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $surveyId,
        public readonly string $reminderKey,
    ) {}

    public function handle(QuestJourneySurveyService $surveys): void
    {
        $survey = QuestJourneySurvey::query()
            ->with(['user', 'quest'])
            ->find($this->surveyId);

        if (! $survey || ! $survey->user?->email) {
            return;
        }

        if ($survey->isSubmitted() || $survey->isExpired()) {
            return;
        }

        if ($survey->email_sent_at === null) {
            return;
        }

        $sent = $survey->reminders_sent ?? [];
        if (in_array($this->reminderKey, $sent, true)) {
            return;
        }

        if ($this->reminderKey === 'before_expiry') {
            $hoursBefore = (int) config('quest_journey_survey.reminders.before_expiry_hours', 6);
            if ($survey->expires_at && now()->lt($survey->expires_at->copy()->subHours($hoursBefore))) {
                self::dispatch($survey->id, $this->reminderKey)
                    ->delay($survey->expires_at->copy()->subHours($hoursBefore));

                return;
            }
        }

        Mail::to($survey->user->email)->send(new QuestJourneySurveyMail($survey, $this->reminderKey));

        $survey->update([
            'reminders_sent' => array_values(array_unique([...$sent, $this->reminderKey])),
        ]);
    }
}
