<?php

namespace App\Notifications;

use App\Models\Quest;
use App\Models\QuestConversationMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class QuestThreadMessageNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Quest $quest,
        public User $sender,
        public QuestConversationMessage $message,
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
        $path = $notifiable->role?->slug === 'freelancer'
            ? route('quests.messages.show', [$this->quest->getRouteKey()], absolute: true)
            : route('quests.messages.show', [$this->quest->getRouteKey(), $this->counterpartyRouteKey()], absolute: true);

        $first = $notifiable->first_name ?: $notifiable->name;
        $senderName = $this->sender->first_name ?: $this->sender->name;

        return (new MailMessage)
            ->subject(__('New message about: :title', ['title' => $this->quest->title]))
            ->markdown('mail.quests.thread-message', [
                'firstName' => $first,
                'senderName' => $senderName,
                'questTitle' => $this->quest->title,
                'preview' => Str::limit(strip_tags($this->message->body), 200),
                'ctaUrl' => $path,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $href = $notifiable->role?->slug === 'freelancer'
            ? route('quests.messages.show', [$this->quest->getRouteKey()], absolute: false)
            : route('quests.messages.show', [$this->quest->getRouteKey(), $this->counterpartyRouteKey()], absolute: false);

        return [
            'headline' => __('New quest message'),
            'title' => __('New quest message'),
            'quest_id' => $this->quest->id,
            'quest_title' => $this->quest->title,
            'sender_id' => $this->sender->id,
            'counterparty_route_key' => $this->counterpartyRouteKey(),
            'body' => __(':name sent a message.', ['name' => $this->sender->name]),
            'preview' => Str::limit(strip_tags($this->message->body), 160),
            'href' => $href,
            'kind' => 'quest_thread_message',
        ];
    }

    /**
     * Slug preferred for readable URLs; numeric id when slug missing so client links never 404.
     */
    protected function counterpartyRouteKey(): string
    {
        $slug = $this->sender->slug;

        return ($slug !== null && $slug !== '') ? (string) $slug : (string) $this->sender->getKey();
    }
}
