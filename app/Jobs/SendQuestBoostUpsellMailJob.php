<?php

namespace App\Jobs;

use App\Mail\QuestBoostUpsellMail;
use App\Models\Quest;
use App\Services\Quest\ClientQuestBoostService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendQuestBoostUpsellMailJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $questId) {}

    public function handle(ClientQuestBoostService $clientBoosts): void
    {
        $quest = Quest::query()->with('client')->find($this->questId);

        if (! $quest || ! $quest->client?->email) {
            return;
        }

        if ($quest->boost_upsell_email_sent_at !== null) {
            return;
        }

        if ($clientBoosts->hasActiveBoost($quest) || ! $clientBoosts->canPurchase($quest, $quest->client)) {
            return;
        }

        Mail::to($quest->client->email)->send(new QuestBoostUpsellMail($quest));

        $quest->forceFill(['boost_upsell_email_sent_at' => now()])->save();
    }
}
