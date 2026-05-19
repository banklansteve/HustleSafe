<?php

namespace App\Jobs;

use App\Models\EmailBroadcast;
use App\Models\EmailBroadcastRecipient;
use App\Models\User;
use App\Services\Admin\EmailBroadcastService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailBroadcastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $broadcastId) {}

    public function handle(EmailBroadcastService $service): void
    {
        $broadcast = EmailBroadcast::query()->findOrFail($this->broadcastId);
        $broadcast->forceFill(['status' => 'processing', 'sent_at' => now()])->save();

        $broadcast->recipients()->where('status', 'queued')->with('user')->chunkById(100, function ($recipients) use ($broadcast, $service): void {
            foreach ($recipients as $recipient) {
                /** @var EmailBroadcastRecipient $recipient */
                $user = $recipient->user;
                if (! $user instanceof User) {
                    $recipient->forceFill(['status' => 'bounced', 'bounced_at' => now()])->save();
                    continue;
                }

                $html = $service->renderForUser($service->wrapHtml($broadcast->body_html, (string) $broadcast->preview_text), $user);
                Mail::html($html, function ($message) use ($broadcast, $recipient): void {
                    $message->to($recipient->email)
                        ->subject($broadcast->subject)
                        ->from(config('mail.from.address'), $broadcast->from_name ?: config('app.name'))
                        ->replyTo($broadcast->reply_to ?: config('mail.from.address'));
                });

                $recipient->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
                $broadcast->incrementEach(['sent_count' => 1, 'delivered_count' => 1]);
            }
        });

        $broadcast->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
    }
}
