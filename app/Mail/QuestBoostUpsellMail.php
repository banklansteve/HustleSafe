<?php

namespace App\Mail;

use App\Models\Quest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuestBoostUpsellMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quest $quest) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Get more eyes on your quest: :title', ['title' => $this->quest->title]),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.quests.boost-upsell',
            with: [
                'questTitle' => $this->quest->title,
                'ctaUrl' => route('quests.show', $this->quest, absolute: true).'#boost-quest',
            ],
        );
    }
}
