<?php

namespace App\Services\Disputes;

use App\Models\DisputeSettlementOffer;
use App\Models\Quest;
use App\Models\User;
use App\Services\Admin\FinancialControlCentreService;
use App\Services\QuestCompletionEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeEscrowSettlementService
{
    public function __construct(
        private readonly FinancialControlCentreService $financial,
        private readonly QuestCompletionEventLogger $events,
    ) {}

    public function executeAcceptedSettlement(DisputeSettlementOffer $offer): void
    {
        $dispute = $offer->dispute;
        $quest = $dispute?->quest;
        if ($quest === null) {
            return;
        }

        if (! in_array($quest->escrow_status, ['funded', 'held', 'frozen', 'partially_released'], true)) {
            return;
        }

        $held = $this->heldMinor($quest);
        if ($held <= 0) {
            return;
        }

        $clientPercent = max(0, min(100, (int) $offer->client_share_percent));
        $clientMinor = (int) round($held * ($clientPercent / 100));
        $freelancerMinor = max(0, $held - $clientMinor);

        $admin = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        if ($admin === null) {
            throw ValidationException::withMessages(['dispute' => [__('No super admin available to execute escrow settlement.')]]);
        }

        $reason = __('Dispute settlement offer #:id accepted', ['id' => $offer->id]);

        if ($clientMinor >= $held) {
            $this->financial->applyEscrowAction($quest, $admin, [
                'action' => 'full_refund',
                'reason' => $reason,
            ]);
        } elseif ($freelancerMinor >= $held) {
            $this->financial->applyEscrowAction($quest, $admin, [
                'action' => 'manual_release',
                'reason' => $reason,
            ]);
        } else {
            $this->financial->applyEscrowAction($quest, $admin, [
                'action' => 'partial_refund',
                'amount' => round($clientMinor / 100, 2),
                'freelancer_amount' => round($freelancerMinor / 100, 2),
                'reason' => $reason,
            ]);
        }

        $quest->refresh();
        $quest->update([
            'status' => \App\Enums\QuestStatus::Closed,
            'closure_type' => 'dispute_settlement',
            'completed_at' => now(),
        ]);

        $this->events->record($quest->fresh(), 'dispute_settlement_executed', $admin, null, [
            'offer_id' => $offer->id,
            'client_minor' => $clientMinor,
            'freelancer_minor' => $freelancerMinor,
            'held_minor' => $held,
        ]);
    }

    protected function heldMinor(Quest $quest): int
    {
        $escrow = \App\Models\PaymentEscrow::query()->where('quest_id', $quest->id)->first();
        if ($escrow !== null) {
            return $escrow->releasableMinor();
        }

        return max(0, (int) ($quest->paid_out_minor ?? 0));
    }
}
