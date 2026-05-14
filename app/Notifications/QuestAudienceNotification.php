<?php

namespace App\Notifications;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class QuestAudienceNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        /** follow|match|tag */
        public string $kind,
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
        $quest = $this->quest->loadMissing(['client:id,first_name,name', 'questCategory:id,name', 'stateModel:id,name']);
        $url = route('quests.show', $quest, absolute: true);
        $clientName = $quest->client?->first_name ?: $quest->client?->name ?: 'A client';
        $ref = $quest->reference_code ?? $quest->uuid;
        $first = $notifiable->first_name ?: $notifiable->name;

        $intro = match ($this->kind) {
            'follow' => __(':client just posted a new quest — and you follow them on HustleSafe.', ['client' => $clientName]),
            'match' => __('We found an open quest that lines up with your saved work categories.', ['client' => $clientName]),
            'tag' => __(':client tagged you on a quest they want you to see.', ['client' => $clientName]),
            default => __('A new quest is live on HustleSafe.'),
        };

        return (new MailMessage)
            ->subject(__('New quest: :title', ['title' => $quest->title]))
            ->markdown('mail.quests.audience', [
                'firstName' => $first,
                'intro' => $intro,
                'questTitle' => $quest->title,
                'category' => $quest->questCategory?->name,
                'location' => trim(implode(' · ', array_filter([$quest->city, $quest->stateModel?->name]))),
                'reference' => $ref,
                'budgetLine' => $quest->budget_amount_minor
                    ? __('Budget around :amount.', ['amount' => '₦'.number_format((int) $quest->budget_amount_minor / 100, 0, '.', ',')])
                    : null,
                'ctaUrl' => $url,
                'kind' => $this->kind,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $quest = $this->quest->loadMissing(['client:id,first_name,name', 'questCategory:id,name']);

        $headline = match ($this->kind) {
            'follow' => __('New quest from someone you follow'),
            'match' => __('Quest matches your categories'),
            'tag' => __('You were tagged on a quest'),
            default => __('New quest on HustleSafe'),
        };

        $preview = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $quest->description))), 160) ?: null;

        return [
            'headline' => $headline,
            'title' => $headline,
            'quest_title' => $quest->title,
            'body' => $quest->title,
            'preview' => $preview,
            'href' => route('quests.show', $quest, absolute: false),
            'kind' => $this->kind,
            'category' => $quest->questCategory?->name,
        ];
    }
}
