<?php

namespace App\Notifications;

use App\Models\UserVerification;
use App\Services\Verification\UserVerificationPresentationService;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserVerificationDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly UserVerification $verification,
        private readonly string $action,
        private readonly string $reason = '',
        private readonly ?string $reasonCode = null,
        private readonly ?string $reasonLabel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $presentation = app(UserVerificationPresentationService::class)->forReview($this->verification);
        $firstName = explode(' ', (string) $notifiable->name)[0] ?: $notifiable->name;

        return (new MailMessage)
            ->subject($this->mailSubject())
            ->markdown('mail.verification.decision', [
                'firstName' => $firstName,
                'headline' => $this->headline(),
                'body' => $this->bodyText(),
                'reason' => $this->reasonForUser() ?: null,
                'verificationLabel' => $presentation['category_label'],
                'statusLabel' => $presentation['status_label'],
                'ctaUrl' => $this->actionUrl(),
                'ctaLabel' => __('View verification feedback'),
            ]);
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->headline(),
            'body' => $this->bodyText(),
            'source' => 'verification_team',
            'category' => 'kyc',
            'action_url' => $this->actionUrl(),
            'verification_id' => $this->verification->id,
            'action' => $this->action,
            'reason_code' => $this->reasonCode,
            'reason_label' => $this->reasonLabel,
        ];
    }

    private function actionUrl(): string
    {
        return route('verifications.index', ['verification_id' => $this->verification->id]);
    }

    private function reasonForUser(): string
    {
        if ($this->reasonLabel) {
            return $this->reason !== '' && ! str_starts_with($this->reason, $this->reasonLabel)
                ? $this->reason
                : ($this->reason !== '' ? $this->reason : $this->reasonLabel);
        }

        return $this->reason;
    }

    private function mailSubject(): string
    {
        return match ($this->action) {
            'approve' => __('Your verification was approved'),
            'request_corrections' => __('Action needed on your verification'),
            default => __('Update on your verification submission'),
        };
    }

    private function headline(): string
    {
        return match ($this->action) {
            'approve' => __('Verification approved'),
            'request_corrections' => __('Please update your submission'),
            'reject' => __('Verification not approved'),
            default => __('Verification reviewed'),
        };
    }

    private function bodyText(): string
    {
        $type = app(UserVerificationPresentationService::class)->forReview($this->verification)['category_label'];

        return match ($this->action) {
            'approve' => __('Your :type submission has been approved. Your trust level and platform limits may update shortly.', ['type' => $type]),
            'request_corrections' => __('We reviewed your :type submission and need corrections before we can approve it. Open Trust & verification for details.', ['type' => $type]),
            'reject' => __('We could not approve your :type submission. Open Trust & verification to see the reviewer feedback.', ['type' => $type]),
            default => __('Your :type submission has been reviewed. Sign in to see the latest status.', ['type' => $type]),
        };
    }
}
