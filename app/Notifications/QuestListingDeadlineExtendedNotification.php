<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Notifications\Concerns\SendsBrandedMail;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestListingDeadlineExtendedNotification extends Notification
{
    use Queueable, SendsBrandedMail;

    public function __construct(
        public Quest $quest,
        public int $daysAdded,
        public \DateTimeInterface $newExpiry,
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
        return $this->brandedMail(
            subject: __('Quest deadline extended — :title', ['title' => $this->quest->title]),
            headline: __('Proposal deadline extended'),
            notifiable: $notifiable,
            lines: [
                __('The client extended the proposal deadline for “:title” by :days day(s).', [
                    'title' => $this->quest->title,
                    'days' => $this->daysAdded,
                ]),
                __('New close date: :when', [
                    'when' => \Carbon\Carbon::parse($this->newExpiry)->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                ]),
            ],
            ctaUrl: route('quests.show', $this->quest, absolute: true),
            ctaLabel: __('View quest'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'quest_listing_deadline_extended',
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'days_added' => $this->daysAdded,
            'new_expires_at' => \Carbon\Carbon::parse($this->newExpiry)->toIso8601String(),
            'label' => __('Deadline extended'),
            'line' => __(':title — proposal window extended by :days day(s).', [
                'title' => $this->quest->title,
                'days' => $this->daysAdded,
            ]),
            'href' => route('quests.show', $this->quest, absolute: false),
        ];
    }
}
