<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Models\QuestDeliverySubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestDeliverableSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public QuestDeliverySubmission $submission,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $deadline = $this->quest->delivery_review_deadline_at?->timezone(config('app.timezone'))->format('j M Y, g:i A');
        $url = route('quests.proposals.show', [$this->quest, $this->quest->acceptedOffer], absolute: true);

        $mail = (new MailMessage)
            ->subject(__('Deliverable ready for review — :title', ['title' => $this->quest->title]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->first_name ?? $notifiable->name]))
            ->line(__('Your freelancer submitted work for review.'));

        if ($deadline) {
            $mail->line(__('Please review by :when. If you take no action and no dispute is open, escrow may auto-release after the review window.', ['when' => $deadline]));
        }

        return $mail->action(__('Review deliverable'), $url);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'quest_deliverable_submitted',
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'submission_id' => $this->submission->id,
            'url' => route('quests.proposals.show', [$this->quest, $this->quest->acceptedOffer]),
        ];
    }
}
