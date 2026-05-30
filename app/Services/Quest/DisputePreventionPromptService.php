<?php

namespace App\Services\Quest;

use App\Models\Quest;
use App\Models\User;
use App\Support\EscrowReleasePolicy;

class DisputePreventionPromptService
{
    /**
     * @return list<array{key: string, tone: string, title: string, body: string}>
     */
    public function promptsFor(Quest $quest, ?User $viewer): array
    {
        if ($viewer === null || ! $quest->isParty($viewer)) {
            return [];
        }

        $prompts = [];
        $isClient = (int) $viewer->id === (int) $quest->client_id;
        $isFreelancer = (int) $viewer->id === (int) $quest->freelancer_id;

        if ($isClient && $quest->escrow_status === 'awaiting_funding' && $quest->accepted_quest_offer_id) {
            $prompts[] = $this->prompt('fund_before_work', 'info',
                __('Fund escrow before work begins'),
                __('Once funded, the freelancer can start safely. If delivery does not meet scope later, request a revision before marking complete — disputes are a last resort.'),
            );
        }

        if ($quest->escrow_funded_at !== null && in_array($quest->escrow_status, ['funded', 'partially_released'], true)) {
            if ($isClient) {
                $prompts[] = $this->prompt('escrow_funded_client', 'info',
                    __('Escrow is protected'),
                    __('If the delivery does not meet the agreed scope, you can request a revision before marking complete. Disputes are for when direct conversation does not resolve the issue.'),
                );
            }
            if ($isFreelancer) {
                $prompts[] = $this->prompt('escrow_funded_freelancer', 'info',
                    __('Work is covered by escrow'),
                    __('Keep progress updates in the quest thread. If scope changes, agree in writing before extra work — this prevents misunderstandings later.'),
                );
            }
        }

        if ($isClient && EscrowReleasePolicy::canAcknowledgeDelivery($quest, $viewer)) {
            $prompts[] = $this->prompt('review_before_complete', 'amber',
                __('Review before you confirm'),
                __('If the delivery does not meet the agreed scope, message the freelancer and request a revision before marking complete. Only confirm when you are satisfied with the deliverables.'),
            );
        }

        if ($isClient && $quest->delivery_acknowledged_at !== null && ! EscrowReleasePolicy::canReleaseFunds($quest, $viewer)) {
            $prompts[] = $this->prompt('release_cooldown', 'sky',
                __('Funds stay protected'),
                __('Escrow remains locked during the protection window. Use this time to verify deliverables — release only when you are ready.'),
            );
        }

        $disputeUi = \App\Support\QuestCommerceUi::disputeForQuest($quest, $viewer);
        if ($disputeUi['can_open_dispute'] ?? false) {
            $prompts[] = $this->prompt('before_dispute', 'amber',
                __('Try a direct message first'),
                __('Before raising a dispute, consider messaging the other party in the quest thread. Most issues resolve faster with a clear, documented conversation.'),
            );
        }

        return $prompts;
    }

    /**
     * @return array{key: string, tone: string, title: string, body: string}
     */
    private function prompt(string $key, string $tone, string $title, string $body): array
    {
        return compact('key', 'tone', 'title', 'body');
    }
}
