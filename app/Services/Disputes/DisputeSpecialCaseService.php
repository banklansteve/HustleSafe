<?php

namespace App\Services\Disputes;

use App\Models\DisputeEvent;
use App\Models\DisputePrecedent;
use App\Models\QuestDispute;
use App\Models\User;
use App\Services\Disputes\DisputeSuperAdminAlertService;

class DisputeSpecialCaseService
{
    public function __construct(private readonly DisputeSuperAdminAlertService $superAdminAlerts) {}

    public function flagChargebackRisk(QuestDispute $dispute, User $actor, array $data): QuestDispute
    {
        $dispute->forceFill(['chargeback_risk_flagged_at' => now()])->save();
        $this->recordEvent($dispute, $actor, 'admin.chargeback_risk_flagged', ['note' => $data['note'] ?? null]);

        return $dispute->fresh();
    }

    public function openPatternInvestigation(QuestDispute $dispute, User $actor, array $data): QuestDispute
    {
        $dispute->loadMissing('quest');
        $dispute->forceFill(['pattern_investigation_at' => now()])->save();

        $linked = QuestDispute::query()
            ->where('id', '!=', $dispute->id)
            ->where(function ($q) use ($dispute): void {
                $q->where('opened_by_user_id', $dispute->opened_by_user_id)
                    ->orWhereHas('quest', function ($quest) use ($dispute): void {
                        $quest->where('client_id', $dispute->quest?->client_id)
                            ->orWhere('freelancer_id', $dispute->quest?->freelancer_id);
                    });
            })
            ->limit(10)
            ->pluck('id')
            ->all();

        $this->recordEvent($dispute, $actor, 'admin.pattern_investigation_opened', [
            'note' => $data['note'] ?? null,
            'linked_dispute_ids' => $linked,
        ]);

        return $dispute->fresh();
    }

    public function createPrecedent(QuestDispute $dispute, User $actor, array $data): DisputePrecedent
    {
        $precedent = DisputePrecedent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'created_by_user_id' => $actor->id,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'category' => $data['category'] ?? null,
            'linked_dispute_ids' => $data['linked_dispute_ids'] ?? [],
        ]);

        $this->recordEvent($dispute, $actor, 'admin.precedent_created', [
            'precedent_id' => $precedent->id,
            'title' => $precedent->title,
        ]);

        return $precedent;
    }

    private function recordEvent(QuestDispute $dispute, User $actor, string $action, array $properties = []): void
    {
        DisputeEvent::query()->create([
            'quest_dispute_id' => $dispute->id,
            'actor_user_id' => $actor->id,
            'action' => $action,
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }
}
