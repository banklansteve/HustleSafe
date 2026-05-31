<?php

namespace App\Notifications\Concerns;

use App\Support\BrandedMail;
use Illuminate\Notifications\Messages\MailMessage;

trait SendsBrandedMail
{
    /**
     * @param  list<string>  $lines
     */
    protected function brandedMail(
        string $subject,
        string $headline,
        object $notifiable,
        array $lines,
        ?string $panel = null,
        ?string $ctaUrl = null,
        ?string $ctaLabel = null,
        ?string $footerLine = null,
    ): MailMessage {
        return (new MailMessage)
            ->subject($subject)
            ->markdown('mail.branded.notice', [
                'headline' => $headline,
                'firstName' => BrandedMail::firstName($notifiable),
                'lines' => $lines,
                'panel' => $panel,
                'ctaUrl' => $ctaUrl,
                'ctaLabel' => $ctaLabel,
                'secondaryCtaUrl' => null,
                'secondaryCtaLabel' => null,
                'footerLine' => $footerLine,
            ]);
    }
}
