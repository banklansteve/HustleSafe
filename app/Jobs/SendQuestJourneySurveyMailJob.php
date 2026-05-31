<?php

namespace App\Jobs;

use App\Mail\QuestJourneySurveyMail;
use App\Models\QuestJourneySurvey;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendQuestJourneySurveyMailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $surveyId) {}

    public function handle(): void
    {
        $survey = QuestJourneySurvey::query()
            ->with(['user', 'quest'])
            ->find($this->surveyId);

        if (! $survey || ! $survey->user?->email) {
            return;
        }

        if ($survey->email_sent_at !== null) {
            return;
        }

        if ($survey->isExpired()) {
            return;
        }

        if ($survey->email_send_at && $survey->email_send_at->isFuture()) {
            self::dispatch($survey->id)->delay($survey->email_send_at);

            return;
        }

        Mail::to($survey->user->email)->send(new QuestJourneySurveyMail($survey));
        $survey->update(['email_sent_at' => now()]);

        app(\App\Services\Quest\QuestJourneySurveyService::class)->scheduleReminders($survey->fresh());
    }
}
