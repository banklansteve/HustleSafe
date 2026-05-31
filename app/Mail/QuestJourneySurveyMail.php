<?php

namespace App\Mail;

use App\Models\QuestJourneySurvey;
use App\Services\Quest\QuestJourneySurveyService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestJourneySurveyMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $subjectLine;

    public readonly string $opener;

    public readonly string $firstQuestionLabel;

    public readonly string $headline;

    public readonly bool $isReminder;

    /** @var list<array{value: string, label: string, url: string}> */
    public readonly array $embeddedOptions;

    public function __construct(
        public readonly QuestJourneySurvey $survey,
        public readonly ?string $reminderKey = null,
    ) {
        $service = app(QuestJourneySurveyService::class);
        $this->isReminder = $reminderKey !== null;
        $this->subjectLine = $service->emailSubject($survey, $reminderKey);
        $this->opener = $service->emailOpener($survey, $reminderKey);
        $this->headline = $service->emailHeadline($reminderKey);
        $this->firstQuestionLabel = $service->firstQuestionLabel($survey->cohort);
        $this->embeddedOptions = $service->emailEmbeddedOptions($survey);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quest-journey-survey',
        );
    }
}
