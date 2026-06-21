<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractStatus;
use App\Models\QuestContract;
use App\Support\NgnMoney;
use Illuminate\Support\Str;

final class ContractQualityAuditService
{
    public function __construct(
        private readonly ContractManagementDashboardService $dashboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function randomSample(int $sampleSize = 50, ?string $status = null): array
    {
        $sampleSize = max(5, min(200, $sampleSize));

        $query = QuestContract::query()
            ->with([
                'quest:id,title',
                'client:id,name',
                'freelancer:id,name',
            ])
            ->whereIn('status', [
                ContractStatus::Active,
                ContractStatus::Completed,
                ContractStatus::Disputed,
            ])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->inRandomOrder()
            ->limit($sampleSize);

        $contracts = $query->get();

        return [
            'sample_size' => $contracts->count(),
            'requested_size' => $sampleSize,
            'generated_at' => now()->timezone('Africa/Lagos')->toIso8601String(),
            'items' => $contracts->map(function (QuestContract $contract) {
                $risk = $this->dashboard->assessRisk($contract);
                $financial = is_array($contract->financial_snapshot) ? $contract->financial_snapshot : [];
                $amountMinor = (int) ($financial['grand_total_minor'] ?? $financial['total_minor'] ?? 0);

                return [
                    'id' => $contract->id,
                    'reference_code' => $contract->reference_code,
                    'quest_title' => $contract->quest?->title,
                    'client_name' => $contract->client?->name,
                    'freelancer_name' => $contract->freelancer?->name,
                    'status' => $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
                    'status_label' => $contract->status instanceof ContractStatus ? $contract->status->label() : (string) $contract->status,
                    'amount_formatted' => NgnMoney::format($amountMinor),
                    'risk_level' => $risk['level'],
                    'risk_reasons' => array_slice($risk['reasons'] ?? [], 0, 3),
                    'flagged_for_review' => (bool) $contract->flagged_for_review,
                    'audit_prompt' => $this->auditPrompt($contract, $risk),
                ];
            })->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $risk
     */
    private function auditPrompt(QuestContract $contract, array $risk): string
    {
        if ($contract->flagged_for_review) {
            return 'Verify staff flag reason and whether escalation was appropriate.';
        }

        if (($risk['level'] ?? '') === 'critical') {
            return 'High-priority QA: confirm delivery timeline, escrow state, and party communications.';
        }

        if ($contract->status === ContractStatus::Completed) {
            return 'Spot-check delivery quality, client satisfaction signals, and fee accuracy.';
        }

        return 'Random sample: review contract terms snapshot and milestone alignment.';
    }
}
