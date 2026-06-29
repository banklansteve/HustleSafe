<?php

namespace App\Services\Admin\ContractManagement;

use App\Enums\ContractStatus;
use App\Models\FinancialEscrowRecord;
use App\Models\PaymentEscrow;
use App\Models\QuestContract;
use App\Models\QuestContractEvent;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Models\QuestDeliverySubmission;
use App\Models\User;
use App\Enums\ContractPatrolFlagStatus;
use App\Models\ContractPatrolFlag;
use App\Support\EscrowAutoReleasePolicy;
use App\Support\NgnMoney;
use App\Support\PlatformSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ContractManagementDetailService
{
    public function __construct(
        private readonly ContractManagementDashboardService $dashboard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(QuestContract $contract, bool $isSuperAdmin): array
    {
        $contract->loadMissing([
            'quest.questCategory.parent',
            'quest.stateModel',
            'quest.localGovernment',
            'quest.latestDeliverySubmission',
            'quest.paymentEscrow',
            'offer:id,reference_code,pitch',
            'client:id,name,username,slug,email,current_verification_level,avatar_url',
            'client.trustMetrics',
            'freelancer:id,name,username,slug,email,current_verification_level,avatar_url,headline',
            'freelancer.trustMetrics',
            'activeDispute.openedBy:id,name',
            'deliverables',
            'milestones',
            'amendments',
            'deliveryExtensions',
            'events.user:id,name',
            'flaggedForReviewBy:id,name',
        ]);

        $quest = $contract->quest;
        $financial = is_array($contract->financial_snapshot) ? $contract->financial_snapshot : [];
        $amountMinor = (int) ($financial['grand_total_minor'] ?? $financial['total_minor'] ?? $quest?->budget_amount_minor ?? 0);
        $risk = $this->dashboard->assessRisk($contract);
        $delivery = $this->deliverySection($contract);
        $escrow = $this->escrowSection($contract, $amountMinor);
        $dispute = $this->disputeSection($contract);
        $milestones = $this->milestonesSection($contract);

        return [
            'contract' => [
                'id' => $contract->id,
                'reference_code' => $contract->reference_code,
                'status' => $contract->status instanceof ContractStatus ? $contract->status->value : (string) $contract->status,
                'status_label' => $contract->status instanceof ContractStatus ? $contract->status->label() : (string) $contract->status,
                'flagged_for_review' => (bool) $contract->flagged_for_review,
                'flagged_for_review_reason' => $contract->flagged_for_review_reason,
                'flagged_for_review_at' => $contract->flagged_for_review_at?->timezone('Africa/Lagos')->toIso8601String(),
                'flagged_by' => $contract->flaggedForReviewBy?->name,
            ],
            'quest' => [
                'title' => $quest?->title,
                'reference_code' => $quest?->reference_code,
                'route_key' => $quest?->getRouteKey(),
                'category' => $quest?->questCategory?->parent
                    ? $quest->questCategory->parent->name.' / '.$quest->questCategory->name
                    : $quest?->questCategory?->name,
                'posted_at' => $quest?->created_at?->timezone('Africa/Lagos')->format('j M Y'),
                'location' => trim(collect([$quest?->stateModel?->name, $quest?->localGovernment?->name])->filter()->implode(', ')),
            ],
            'parties' => [
                'client' => $this->partyCard($contract->client, 'client'),
                'freelancer' => $this->partyCard($contract->freelancer, 'freelancer'),
            ],
            'financial' => $escrow['summary'],
            'timeline' => $this->timelineSection($contract),
            'delivery' => $delivery,
            'dispute' => $dispute,
            'escrow' => $escrow,
            'risk' => $risk,
            'patrol_flags' => $this->patrolFlags($contract),
            'milestones' => $milestones,
            'compliance' => $this->complianceSection($contract),
            'audit_log' => $this->auditLog($contract),
            'staff_notes' => $this->staffNotes($contract),
            'quality_reviews' => $this->qualityReviews($contract),
            'messages' => $this->messageTranscript($contract),
            'links' => [
                'full_contract' => route('admin.contracts.view', $contract->reference_code),
                'escrow_ledger' => $quest ? route('admin.financial-audit.escrow-ledger', ['q' => $contract->reference_code]) : null,
                'dispute_console' => $dispute !== null
                    ? route('admin.disputes.index', ['q' => $contract->reference_code])
                    : null,
            ],
            'is_super_admin' => $isSuperAdmin,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function partyCard(?User $user, string $role): ?array
    {
        if ($user === null) {
            return null;
        }

        $completedContracts = QuestContract::query()
            ->where($role === 'client' ? 'client_id' : 'freelancer_id', $user->id)
            ->where('status', ContractStatus::Completed)
            ->count();

        $activeContracts = QuestContract::query()
            ->where($role === 'client' ? 'client_id' : 'freelancer_id', $user->id)
            ->whereIn('status', [ContractStatus::Active, ContractStatus::AmendmentPending, ContractStatus::Disputed])
            ->count();

        $rating = $role === 'client'
            ? $user->trustMetrics?->avg_rating_as_client
            : $user->trustMetrics?->avg_rating_as_freelancer;

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'slug' => $user->slug,
            'tier' => (int) ($user->current_verification_level ?? 0),
            'rating' => $rating !== null ? round((float) $rating, 1) : null,
            'active_contracts' => $activeContracts,
            'completed_contracts' => $completedContracts,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function timelineSection(QuestContract $contract): array
    {
        $quest = $contract->quest;
        $autoRelease = null;

        if ($quest !== null && $contract->status === ContractStatus::Active) {
            $due = app(\App\Services\QuestEngagementLifecycleService::class)->expectedCompletionAt($quest);
            if ($due !== null) {
                $releaseAt = EscrowAutoReleasePolicy::releaseAt($due);
                $autoRelease = [
                    'label' => $releaseAt->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                    'deadline_at' => $releaseAt->toIso8601String(),
                    'seconds_remaining' => EscrowAutoReleasePolicy::secondsUntilRelease($quest),
                ];
            }
        }

        $due = $contract->agreed_delivery_date;
        $overdueDays = ($due && $contract->status === ContractStatus::Active && $quest?->delivery_acknowledged_at === null)
            ? max(0, $due->startOfDay()->diffInDays(now()->startOfDay(), false))
            : 0;

        return [
            'awarded_at' => $contract->generated_at?->timezone('Africa/Lagos')->format('j M Y'),
            'work_started_at' => $contract->activated_at?->timezone('Africa/Lagos')->format('j M Y'),
            'delivery_expected' => $due?->format('j M Y'),
            'delivery_expected_overdue' => $overdueDays > 0,
            'days_overdue' => $overdueDays,
            'delivery_submitted_at' => $quest?->latestDeliverySubmission?->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
            'completed_at' => $contract->completed_at?->timezone('Africa/Lagos')->format('j M Y'),
            'auto_release' => $autoRelease,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function deliverySection(QuestContract $contract): array
    {
        $quest = $contract->quest;
        $submission = $quest?->latestDeliverySubmission;

        return [
            'status' => $this->deliveryStatusLabel($contract),
            'submitted_at' => ($submission?->submitted_at ?? $submission?->created_at)?->timezone('Africa/Lagos')->toIso8601String(),
            'freelancer_notes' => $submission?->summary,
            'delivery_url' => $submission?->delivery_url,
            'files' => collect($submission?->attachments ?? [])->map(fn ($a) => [
                'label' => $a['label'] ?? $a['name'] ?? 'File',
                'url' => $a['url'] ?? null,
                'size_label' => isset($a['size_bytes']) ? $this->formatBytes((int) $a['size_bytes']) : null,
            ])->values()->all(),
            'requirements_checklist' => $this->requirementsChecklist($contract, $submission),
            'deliverables' => $contract->deliverables->map(fn ($d) => [
                'title' => $d->title,
                'description' => $d->description,
            ])->values()->all(),
        ];
    }

    private function deliveryStatusLabel(QuestContract $contract): string
    {
        $quest = $contract->quest;
        if ($quest?->delivery_acknowledged_at) {
            return 'Approved';
        }
        if ($quest?->delivery_revision_requested_at) {
            return 'Revision requested';
        }
        if ($quest?->latest_delivery_submission_id) {
            return 'Awaiting client approval';
        }

        return 'Pending delivery';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function requirementsChecklist(QuestContract $contract, ?QuestDeliverySubmission $submission): array
    {
        $items = [];
        foreach ($contract->deliverables as $deliverable) {
            $items[] = [
                'label' => $deliverable->title,
                'status' => $submission !== null ? 'met' : 'pending',
            ];
        }

        if ($items === []) {
            $snapshot = is_array($contract->quest_snapshot) ? $contract->quest_snapshot : [];

            return collect($snapshot['deliverables'] ?? [])->map(fn ($d) => [
                'label' => is_array($d) ? ($d['title'] ?? 'Deliverable') : (string) $d,
                'status' => $submission !== null ? 'met' : 'pending',
            ])->values()->all();
        }

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    private function escrowSection(QuestContract $contract, int $amountMinor): array
    {
        $quest = $contract->quest;
        $feePercent = PlatformSettings::platformFeePercent();
        $breakdown = NgnMoney::escrowReleaseBreakdown($amountMinor, $feePercent);
        $processorFeeMinor = (int) round($amountMinor * 0.02);
        $freelancerNetMinor = max(0, $breakdown['freelancer_net_minor'] - $processorFeeMinor);

        $paymentEscrow = $quest?->paymentEscrow;
        $ledgerRecord = FinancialEscrowRecord::query()
            ->where('quest_contract_id', $contract->id)
            ->latest('id')
            ->first();

        $statusLabel = match ($quest?->escrow_status) {
            'funded', 'partially_released' => 'Holding in escrow',
            'released' => 'Released',
            'awaiting_funding' => 'Awaiting funding',
            'refunded' => 'Refunded',
            default => ucfirst(str_replace('_', ' ', (string) ($quest?->escrow_status ?? 'unknown'))),
        };

        return [
            'summary' => [
                'total_minor' => $amountMinor,
                'total_formatted' => NgnMoney::format($amountMinor),
                'platform_fee_minor' => $breakdown['platform_fee_minor'],
                'platform_fee_formatted' => NgnMoney::format($breakdown['platform_fee_minor']),
                'platform_fee_percent' => $feePercent,
                'vat_minor' => $breakdown['vat_minor'],
                'vat_formatted' => NgnMoney::format($breakdown['vat_minor']),
                'processor_fee_minor' => $processorFeeMinor,
                'processor_fee_formatted' => NgnMoney::format($processorFeeMinor),
                'freelancer_net_minor' => $freelancerNetMinor,
                'freelancer_net_formatted' => NgnMoney::format($freelancerNetMinor),
                'status_label' => $statusLabel,
                'funded_at' => $contract->escrow_funded_at?->timezone('Africa/Lagos')->format('j M Y'),
                'escrow_status' => $quest?->escrow_status,
            ],
            'payment_escrow' => $paymentEscrow ? [
                'reference' => $paymentEscrow->reference,
                'status' => $paymentEscrow->status,
                'amount_formatted' => NgnMoney::format((int) $paymentEscrow->amount_minor),
            ] : null,
            'ledger_record_id' => $ledgerRecord?->id,
            'ledger_entries' => $this->ledgerEntries($amountMinor, $breakdown, $processorFeeMinor, $freelancerNetMinor),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function ledgerEntries(int $grossMinor, array $breakdown, int $processorFeeMinor, int $freelancerNetMinor): array
    {
        $running = $grossMinor;

        return [
            ['event' => 'Escrow funded', 'debit' => NgnMoney::format($grossMinor), 'credit' => '—', 'balance' => NgnMoney::format($running)],
            ['event' => 'Platform fee deducted', 'debit' => '—', 'credit' => NgnMoney::format($breakdown['platform_fee_minor']), 'balance' => NgnMoney::format($running -= $breakdown['platform_fee_minor'])],
            ['event' => 'VAT deducted', 'debit' => '—', 'credit' => NgnMoney::format($breakdown['vat_minor']), 'balance' => NgnMoney::format($running -= $breakdown['vat_minor'])],
            ['event' => 'Processor fee', 'debit' => '—', 'credit' => NgnMoney::format($processorFeeMinor), 'balance' => NgnMoney::format($freelancerNetMinor)],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function disputeSection(QuestContract $contract): ?array
    {
        $disputes = \App\Models\QuestDispute::query()
            ->where('quest_id', $contract->quest_id)
            ->where('quest_offer_id', $contract->quest_offer_id)
            ->with('openedBy:id,name')
            ->orderByDesc('id')
            ->get();

        if ($disputes->isEmpty() && $contract->status !== ContractStatus::Disputed) {
            return null;
        }

        $active = $disputes->first(fn (\App\Models\QuestDispute $d) => $d->isActiveOnContract());

        return [
            'active' => $active !== null,
            'count' => $disputes->count(),
            'items' => $disputes->map(fn (\App\Models\QuestDispute $dispute) => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'reference' => $dispute->displayReference(),
                'filed_by' => $dispute->openedBy?->name,
                'filed_at' => $dispute->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'status' => $dispute->status instanceof \App\Enums\QuestDisputeStatus ? $dispute->status->value : (string) $dispute->status,
                'management_status_label' => $dispute->management_status?->label(),
                'is_active' => $dispute->isActiveOnContract(),
                'amount_formatted' => NgnMoney::format((int) ($dispute->disputed_amount_minor ?? 0)),
                'resolved_at' => $dispute->resolved_at?->timezone('Africa/Lagos')->format('j M Y'),
                'admin_url' => route('admin.disputes.index', ['q' => $dispute->uuid]),
            ])->values()->all(),
            'summary' => $active
                ? __('Active dispute on this contract')
                : ($disputes->isNotEmpty() ? __(':count dispute(s) on record — none active', ['count' => $disputes->count()]) : 'Contract marked disputed — details loading.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function milestonesSection(QuestContract $contract): array
    {
        $milestones = $contract->milestones;
        if ($milestones->isEmpty()) {
            return ['recurring' => false, 'items' => []];
        }

        $totalMinor = (int) ($contract->financial_snapshot['grand_total_minor'] ?? 0);
        $paidMinor = $milestones->sum('value_minor');

        return [
            'recurring' => $milestones->count() > 1,
            'items' => $milestones->map(fn ($m) => [
                'label' => $m->name ?? ('Installment '.$m->position),
                'period' => $m->deadline_date?->format('j M Y'),
                'status' => 'pending',
                'amount_formatted' => NgnMoney::format((int) ($m->value_minor ?? 0)),
            ])->values()->all(),
            'paid_formatted' => NgnMoney::format((int) $paidMinor),
            'total_formatted' => NgnMoney::format($totalMinor),
            'completion_percent' => $totalMinor > 0 ? round(($paidMinor / $totalMinor) * 100, 1) : 0,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function complianceSection(QuestContract $contract): array
    {
        $clientTier = (int) ($contract->client?->current_verification_level ?? 0);
        $freelancerTier = (int) ($contract->freelancer?->current_verification_level ?? 0);

        return [
            ['label' => 'KYC complete (client)', 'ok' => $clientTier >= 1],
            ['label' => 'KYC complete (freelancer)', 'ok' => $freelancerTier >= 1],
            ['label' => 'Terms agreed (both)', 'ok' => $contract->activated_at !== null],
            ['label' => 'Escrow funded', 'ok' => $contract->escrow_funded_at !== null],
            ['label' => 'No active sanctions flagged', 'ok' => ! $contract->flagged_for_review],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditLog(QuestContract $contract): array
    {
        return $contract->events()
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(40)
            ->get()
            ->map(fn (QuestContractEvent $event) => [
                'at' => $event->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'action' => Str::headline(str_replace('.', ' ', (string) $event->event_type)),
                'by' => $event->user?->name ?? 'System',
                'type' => $event->event_type,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffNotes(QuestContract $contract): array
    {
        return $contract->events()
            ->where('event_type', 'contract.staff_note')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (QuestContractEvent $event) => [
                'at' => $event->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'by' => $event->user?->name,
                'body' => $event->properties['body'] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function qualityReviews(QuestContract $contract): array
    {
        return $contract->events()
            ->where('event_type', 'contract.staff_quality_review')
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (QuestContractEvent $event) => [
                'at' => $event->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'by' => $event->user?->name,
                'rating' => (int) ($event->properties['rating'] ?? 0),
                'notes' => $event->properties['notes'] ?? '',
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function messageTranscript(QuestContract $contract): array
    {
        $quest = $contract->quest;
        if ($quest === null) {
            return ['thread_id' => null, 'messages' => [], 'total' => 0];
        }

        $thread = QuestConversationThread::query()
            ->where('quest_id', $quest->id)
            ->orderByDesc('last_message_at')
            ->first();

        if ($thread === null) {
            return ['thread_id' => null, 'messages' => [], 'total' => 0];
        }

        $messages = QuestConversationMessage::query()
            ->where('quest_conversation_thread_id', $thread->id)
            ->with('user:id,name,username')
            ->orderByDesc('created_at')
            ->limit(80)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (QuestConversationMessage $message) => [
                'id' => $message->id,
                'body' => $message->is_redacted ? ($message->redaction_label ?? '[Redacted]') : $message->body,
                'sender' => $message->user?->name ?? 'Unknown',
                'sender_role' => match (true) {
                    (int) $message->user_id === (int) $contract->client_id => 'client',
                    (int) $message->user_id === (int) $contract->freelancer_id => 'freelancer',
                    default => 'staff',
                },
                'at' => $message->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A'),
                'is_redacted' => (bool) $message->is_redacted,
            ])
            ->all();

        return [
            'thread_id' => $thread->id,
            'messages' => $messages,
            'total' => (int) $thread->messages_count,
        ];
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1).'MB';
        }

        if ($bytes >= 1024) {
            return round($bytes / 1024, 1).'KB';
        }

        return $bytes.'B';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function patrolFlags(QuestContract $contract): array
    {
        if (! Schema::hasTable('contract_patrol_flags')) {
            return [];
        }

        return ContractPatrolFlag::query()
            ->where('quest_contract_id', $contract->id)
            ->whereIn('status', [ContractPatrolFlagStatus::Open, ContractPatrolFlagStatus::Acknowledged])
            ->orderByRaw("FIELD(severity, 'critical', 'high', 'medium', 'low')")
            ->orderByDesc('detected_at')
            ->get()
            ->map(fn (ContractPatrolFlag $flag) => [
                'id' => $flag->id,
                'label' => $flag->flag_type instanceof \App\Enums\ContractPatrolFlagType
                    ? $flag->flag_type->label()
                    : (string) $flag->flag_type,
                'reason' => (string) ($flag->summary ?: 'Patrol review required'),
                'severity' => (string) $flag->severity,
                'status' => $flag->status instanceof ContractPatrolFlagStatus ? $flag->status->value : (string) $flag->status,
                'detected_at' => $flag->detected_at?->timezone('Africa/Lagos')->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
