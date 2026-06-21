<?php

namespace App\Notifications;

use App\Models\ConversationPolicyWarning;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ConversationPolicyWarningReplyStaffNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly ConversationPolicyWarning $warning,
        public readonly User $replier,
        public readonly string $replyPreview,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $reviewId = (int) ($this->warning->thread_review_id ?? 0);

        return [
            'kind' => 'conversation_policy_warning_reply',
            'warning_id' => (int) $this->warning->id,
            'review_id' => $reviewId,
            'title' => __('Policy notice reply from :name', ['name' => $this->replier->name]),
            'body' => str($this->replyPreview)->limit(160)->toString(),
            'href' => $reviewId > 0
                ? route('admin.conversation-monitoring.index', ['review' => $reviewId], absolute: false)
                : route('admin.conversation-monitoring.index', absolute: false),
        ];
    }
}
