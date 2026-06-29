<?php

namespace App\Notifications;

use App\Mail\QuestDisputeOpenedMail;
use App\Models\QuestDispute;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestDisputeOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestDispute $dispute,
        public User $opener,
        public string $recipientRole,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): QuestDisputeOpenedMail
    {
        $hours = (int) config('disputes.self_resolution_response_hours', 48);

        return new QuestDisputeOpenedMail(
            $this->dispute,
            $this->opener,
            $notifiable instanceof User ? $notifiable : User::query()->findOrFail($notifiable->id),
            $this->recipientRole,
            $hours,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->dispute->loadMissing('quest');
        $hours = (int) config('disputes.self_resolution_response_hours', 48);

        return [
            'kind' => 'quest_dispute_opened',
            'headline' => __('Dispute opened on your job'),
            'title' => __('Dispute opened on your job'),
            'body' => __(':name opened a dispute on “:title”. Respond within :hours hours.', [
                'name' => $this->opener->name,
                'title' => $this->dispute->quest?->title,
                'hours' => $hours,
            ]),
            'dispute_uuid' => $this->dispute->uuid,
            'quest_title' => $this->dispute->quest?->title,
            'href' => route('disputes.show', $this->dispute, absolute: false),
        ];
    }
}
