<?php

namespace App\Notifications;

use App\Models\QuestContract;
use App\Models\QuestContractAmendment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ContractAmendmentRespondedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public QuestContract $contract,
        public QuestContractAmendment $amendment,
        public bool $accepted,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => 'contract_amendment_responded',
            'title' => $this->accepted ? __('Amendment accepted') : __('Amendment declined'),
            'body' => __('Your amendment request on :ref was :status.', [
                'ref' => $this->contract->reference_code,
                'status' => $this->accepted ? 'accepted' : 'declined',
            ]),
            'href' => route('contracts.show', $this->contract, absolute: false),
        ];
    }
}
