<?php

namespace App\Services\Disputes;

use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Notifications\SmsGatewayService;

class DisputeSmsNotifier
{
    public function __construct(private readonly SmsGatewayService $sms) {}

    public function notifyParty(User $user, QuestDispute $dispute, string $message): void
    {
        $dispute->loadMissing('quest');
        $ref = $dispute->displayReference();
        $body = __('HustleSafe: :msg Dispute :ref on “:title”.', [
            'msg' => $message,
            'ref' => $ref,
            'title' => $dispute->quest?->title ?? __('your job'),
        ]);

        $this->sms->send($user, $body);
    }

    public function notifyEvidenceDeadline(User $user, QuestDispute $dispute, int $hoursRemaining): void
    {
        $this->notifyParty(
            $user,
            $dispute,
            __('Evidence deadline in :hours hours. Please respond on your dispute page.', ['hours' => $hoursRemaining]),
        );
    }

    public function notifyDecision(User $user, QuestDispute $dispute): void
    {
        $this->notifyParty($user, $dispute, __('A final decision was issued. Open your dispute file for details.'));
    }

    public function notifyMediation(User $user, QuestDispute $dispute, string $when): void
    {
        $this->notifyParty($user, $dispute, __('Mediation session scheduled for :when.', ['when' => $when]));
    }
}
