<?php

namespace App\Services\Disputes;

use App\Models\Quest;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Admin\FinancialControlCentreService;
use App\Services\QuestCompletionEventLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DisputeOutcomeExecutionService
{
    public function __construct(
        private readonly FinancialControlCentreService $financial,
        private readonly QuestCompletionEventLogger $events,
    ) {}

    /**
     * @param  array<string, mixed>  $sanctions
     * @return array{client_minor: int, freelancer_minor: int, held_minor: int}
     */
    public function execute(QuestDispute $dispute, User $superAdmin, int $clientSharePercent, ?string $reason = null, array $sanctions = []): array
    {
        $quest = $dispute->quest;
        if ($quest === null) {
            throw ValidationException::withMessages(['quest' => __('Quest not found for this dispute.')]);
        }

        $clientPercent = max(0, min(100, $clientSharePercent));
        $held = $this->heldMinor($quest);

        if ($held <= 0) {
            return ['client_minor' => 0, 'freelancer_minor' => 0, 'held_minor' => 0];
        }

        $clientMinor = (int) round($held * ($clientPercent / 100));
        $freelancerMinor = max(0, $held - $clientMinor);
        $reason ??= __('Super Admin dispute decision on :ref', ['ref' => $dispute->displayReference()]);

        if ($clientMinor >= $held) {
            $this->financial->applyEscrowAction($quest, $superAdmin, [
                'action' => 'full_refund',
                'reason' => $reason,
            ]);
        } elseif ($freelancerMinor >= $held) {
            $this->financial->applyEscrowAction($quest, $superAdmin, [
                'action' => 'manual_release',
                'reason' => $reason,
            ]);
        } else {
            $this->financial->applyEscrowAction($quest, $superAdmin, [
                'action' => 'partial_refund',
                'amount' => round($clientMinor / 100, 2),
                'freelancer_amount' => round($freelancerMinor / 100, 2),
                'reason' => $reason,
            ]);
        }

        $quest->refresh();
        $quest->update([
            'status' => \App\Enums\QuestStatus::Closed,
            'closure_type' => 'dispute_ruling',
            'completed_at' => $quest->completed_at ?? now(),
        ]);

        $this->events->record($quest->fresh(), 'dispute_ruling_executed', $superAdmin, null, [
            'dispute_id' => $dispute->id,
            'client_minor' => $clientMinor,
            'freelancer_minor' => $freelancerMinor,
            'held_minor' => $held,
            'sanctions' => $sanctions,
        ]);

        return [
            'client_minor' => $clientMinor,
            'freelancer_minor' => $freelancerMinor,
            'held_minor' => $held,
        ];
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
