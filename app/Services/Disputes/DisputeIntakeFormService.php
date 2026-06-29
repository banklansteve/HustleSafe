<?php

namespace App\Services\Disputes;

use App\Enums\QuestDisputeReason;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestConversationMessage;
use App\Models\QuestConversationThread;
use App\Models\QuestOffer;
use App\Models\User;

class DisputeIntakeFormService
{
    /**
     * @return array<string, mixed>
     */
    public function createPayload(Quest $quest, QuestOffer $offer, string $party): array
    {
        $contract = QuestContract::query()
            ->where('quest_id', $quest->id)
            ->where('quest_offer_id', $offer->id)
            ->first();

        $contractValueMinor = (int) ($offer->quoted_amount_minor ?? $quest->budget_amount_minor ?? 0);

        return [
            'reason_groups' => QuestDisputeReason::groupedForParty($party),
            'resolution_options' => $this->resolutionOptions($party),
            'affected_areas' => $this->affectedAreas(),
            'prior_attempt_options' => $this->priorAttemptOptions(),
            'process_options' => $this->processOptions(),
            'availability_options' => $this->availabilityOptions(),
            'contact_methods' => $this->contactMethods(),
            'acknowledgments' => $this->acknowledgmentFields(),
            'prefill' => [
                'timeline' => $this->timelinePrefill($quest, $offer, $contract),
                'contract_value_minor' => $contractValueMinor,
                'conversation_url' => $this->conversationUrl($quest),
                'conversation_message_links' => $this->conversationMessageLinks($quest),
                'conversation_messages_by_date' => $this->conversationMessagesByDate($quest),
                'escalation_date' => now()->timezone('Africa/Lagos')->toDateString(),
            ],
            'limits' => [
                'description_min_words' => (int) config('disputes.intake.description_min_words', 150),
                'description_max_words' => (int) config('disputes.intake.description_max_words', 1000),
                'evidence_max_files' => (int) config('disputes.intake.evidence_max_files', 10),
                'evidence_max_file_kb' => (int) config('disputes.intake.evidence_max_file_kb', 51200),
                'external_links_max' => (int) config('disputes.intake.external_links_max', 10),
                'silence_comms_min_days' => (int) config('disputes.silence_comms_min_days', 5),
            ],
        ];
    }

    /**
     * @return list<array{value: string, label: string, amount_field?: string}>
     */
    public function resolutionOptions(string $party): array
    {
        if ($party === 'client') {
            return [
                ['value' => 'partial_refund', 'label' => __('Partial refund'), 'amount_field' => 'partial_refund_minor'],
                ['value' => 'full_refund', 'label' => __('Full refund')],
                ['value' => 'rework', 'label' => __('Revision/rework')],
                ['value' => 'contract_cancellation', 'label' => __('Contract cancellation')],
                ['value' => 'mediation_investigation', 'label' => __('Mediation/investigation')],
            ];
        }

        return [
            ['value' => 'full_payment_release', 'label' => __('Full payment release')],
            ['value' => 'partial_payment', 'label' => __('Partial payment'), 'amount_field' => 'partial_payment_minor'],
            ['value' => 'dispute_assessment', 'label' => __('Dispute assessment')],
            ['value' => 'contract_cancellation', 'label' => __('Contract cancellation')],
            ['value' => 'client_suspension_warning', 'label' => __('Client suspension/warning')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function affectedAreas(): array
    {
        return [
            ['value' => 'deliverables_quality', 'label' => __('Deliverables quality')],
            ['value' => 'timeline_deadline', 'label' => __('Timeline/deadline')],
            ['value' => 'communication', 'label' => __('Communication')],
            ['value' => 'payment_escrow', 'label' => __('Payment/escrow')],
            ['value' => 'contract_terms', 'label' => __('Contract terms')],
            ['value' => 'conduct_behavior', 'label' => __('Conduct/behavior')],
        ];
    }

    /**
     * @return list<array{value: string, label: string, date_field?: string, count_field?: string}>
     */
    public function priorAttemptOptions(): array
    {
        return [
            ['value' => 'discussed_in_messages', 'label' => __('Discussed informally in platform messages'), 'date_field' => 'last_message_attempt_date'],
            ['value' => 'requested_revisions', 'label' => __('Requested revisions'), 'count_field' => 'revision_request_count'],
            ['value' => 'agreed_extension', 'label' => __('Agreed on extension/modification')],
            ['value' => 'no_prior_discussion', 'label' => __('No prior discussion (direct escalation)')],
            ['value' => 'other', 'label' => __('Other')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function processOptions(): array
    {
        return [
            ['value' => 'mediation', 'label' => __('Mediation (Super admin suggests solution)')],
            ['value' => 'arbitration', 'label' => __('Arbitration (Super admin decides)')],
            ['value' => 'full_investigation', 'label' => __('Full investigation (Detailed forensic review)')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function availabilityOptions(): array
    {
        return [
            ['value' => 'mediation_call', 'label' => __('Available for 1-on-1 mediation call')],
            ['value' => 'willing_to_negotiate', 'label' => __('Willing to negotiate if offered outcome')],
            ['value' => 'open_revised_timeline', 'label' => __('Open to revised timeline')],
            ['value' => 'accept_partial_resolution', 'label' => __('Can accept partial resolution')],
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function contactMethods(): array
    {
        return [
            ['value' => 'email', 'label' => __('Email')],
            ['value' => 'platform', 'label' => __('Platform')],
            ['value' => 'phone', 'label' => __('Phone')],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function acknowledgmentFields(): array
    {
        return [
            ['key' => 'accurate_information', 'label' => __('I have provided accurate information')],
            ['key' => 'binding_resolution', 'label' => __('I understand this dispute is binding once resolved')],
            ['key' => 'accept_platform_decision', 'label' => __('I accept the platform\'s decision')],
            ['key' => 'false_claims_consequence', 'label' => __('I understand false claims can result in account suspension')],
            ['key' => 'evidence_attached', 'label' => __('I have attached all relevant evidence')],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function timelinePrefill(Quest $quest, QuestOffer $offer, ?QuestContract $contract): array
    {
        $awardedAt = $contract?->activated_at
            ?? $contract?->generated_at
            ?? $quest->escrow_funded_at
            ?? $offer->updated_at;

        $workStarted = $contract?->contract_start_date?->toDateString()
            ?? $quest->contract_starts_at?->toDateString()
            ?? $quest->scheduled_start_date?->toDateString();

        $expectedDelivery = $contract?->agreed_delivery_date?->toDateString()
            ?? $quest->delivery_deadline?->toDateString()
            ?? $quest->estimated_delivery_date?->toDateString()
            ?? $quest->due_at?->timezone('Africa/Lagos')->toDateString();

        $deliverableSubmitted = $quest->delivered_at?->timezone('Africa/Lagos')->toDateString();

        return [
            'contract_awarded' => $awardedAt?->timezone('Africa/Lagos')->toDateString(),
            'work_started' => $workStarted,
            'expected_delivery' => $expectedDelivery,
            'deliverable_submitted' => $deliverableSubmitted,
            'issue_first_noticed' => null,
            'informal_resolution_attempted' => null,
            'informal_resolution_date' => null,
            'escalation_date' => now()->timezone('Africa/Lagos')->toDateString(),
        ];
    }

    public function conversationUrl(Quest $quest): ?string
    {
        if ($quest->freelancer_id === null) {
            return null;
        }

        $freelancer = $quest->freelancer;
        if ($freelancer?->slug === null) {
            return route('quests.messages.show', $quest->getRouteKey());
        }

        return route('quests.messages.show', [$quest->getRouteKey(), $freelancer->slug]);
    }

    /**
     * @return list<array{id: int, label: string, url: string, sent_at: string|null}>
     */
    public function conversationMessageLinks(Quest $quest, int $limit = 25): array
    {
        if ($quest->freelancer_id === null) {
            return [];
        }

        $thread = QuestConversationThread::query()
            ->where('quest_id', $quest->id)
            ->where('freelancer_id', $quest->freelancer_id)
            ->first();

        if ($thread === null) {
            return [];
        }

        $baseUrl = $this->conversationUrl($quest) ?? route('quests.messages.show', $quest->getRouteKey());

        return QuestConversationMessage::query()
            ->where('quest_conversation_thread_id', $thread->id)
            ->latest('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (QuestConversationMessage $message) use ($baseUrl): array {
                $preview = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $message->body)) ?? '');
                if (mb_strlen($preview) > 80) {
                    $preview = mb_substr($preview, 0, 77).'…';
                }

                return [
                    'id' => (int) $message->id,
                    'label' => $preview !== '' ? $preview : __('Message #:id', ['id' => $message->id]),
                    'url' => $baseUrl.'#message-'.$message->id,
                    'sent_at' => $message->created_at?->timezone('Africa/Lagos')->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return list<array{date: string, date_label: string, messages: list<array{id: int, label: string, url: string, sent_at: string|null}>}>
     */
    public function conversationMessagesByDate(Quest $quest, int $limit = 50): array
    {
        $links = $this->conversationMessageLinks($quest, $limit);
        $byDate = [];

        foreach ($links as $link) {
            $date = 'unknown';
            $dateLabel = __('Unknown date');

            if (! empty($link['sent_at'])) {
                $carbon = \Carbon\Carbon::parse($link['sent_at'])->timezone('Africa/Lagos');
                $date = $carbon->toDateString();
                $dateLabel = $carbon->format('l, j M Y');
            }

            if (! isset($byDate[$date])) {
                $byDate[$date] = [
                    'date' => $date,
                    'date_label' => $dateLabel,
                    'messages' => [],
                ];
            }

            $byDate[$date]['messages'][] = $link;
        }

        return collect($byDate)
            ->sortKeysDesc()
            ->values()
            ->all();
    }

    /**
     * @param  list<\Illuminate\Http\UploadedFile>  $files
     * @return list<array{path: string, original_name: string, mime_type: string, size_bytes: int, url: string}>
     */
    public function storeEvidenceFiles(string $disputeUuid, array $files): array
    {
        $stored = [];
        $dir = 'disputes/'.$disputeUuid.'/evidence';

        foreach ($files as $file) {
            if (! $file->isValid()) {
                continue;
            }

            $path = $file->store($dir, 'public');
            $stored[] = [
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                'size_bytes' => (int) ($file->getSize() ?: 0),
                'url' => asset('storage/'.$path),
            ];
        }

        return $stored;
    }

    /**
     * @param  array<string, mixed>  $intake
     * @return array<string, mixed>
     */
    public function normalizeIntake(array $intake, QuestDisputeReason $reason, string $party, User $opener, Quest $quest): array
    {
        $intake['category'] = $reason->category()->value;
        $intake['category_label'] = $reason->category()->label();
        $intake['party'] = $party;
        $intake['opened_by_user_id'] = $opener->id;
        $intake['conversation_url'] = $this->conversationUrl($quest);
        $intake['auto_attached_conversation_history'] = true;
        $intake['submitted_at'] = now()->timezone('Africa/Lagos')->toIso8601String();

        if (! isset($intake['timeline']['escalation_date'])) {
            $intake['timeline']['escalation_date'] = now()->timezone('Africa/Lagos')->toDateString();
        }

        $intake['requested_outcome'] = $this->legacyRequestedOutcome($intake['resolution_requested'] ?? null);

        return $intake;
    }

    protected function legacyRequestedOutcome(?string $resolution): ?string
    {
        return match ($resolution) {
            'partial_refund' => 'partial_refund',
            'full_refund' => 'full_refund',
            'rework' => 'rework',
            'full_payment_release' => 'release_payment',
            'partial_payment' => 'partial_refund',
            default => 'other',
        };
    }

    /**
     * @return array<string, string>
     */
    public function displayLabels(): array
    {
        $labels = [];
        foreach ($this->affectedAreas() as $row) {
            $labels['affected_'.$row['value']] = $row['label'];
        }
        foreach ($this->priorAttemptOptions() as $row) {
            $labels['prior_'.$row['value']] = $row['label'];
        }
        foreach ($this->processOptions() as $row) {
            $labels['process_'.$row['value']] = $row['label'];
        }
        foreach ($this->availabilityOptions() as $row) {
            $labels['availability_'.$row['value']] = $row['label'];
        }
        foreach ($this->contactMethods() as $row) {
            $labels['contact_'.$row['value']] = $row['label'];
        }

        return $labels;
    }
}
