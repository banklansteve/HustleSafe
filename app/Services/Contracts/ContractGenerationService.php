<?php

namespace App\Services\Contracts;

use App\Enums\ContractStatus;
use App\Models\PaymentEscrow;
use App\Models\Quest;
use App\Models\QuestContract;
use App\Models\QuestContractDeliverable;
use App\Models\QuestDispute;
use App\Models\QuestOffer;
use App\Models\User;
use App\Notifications\ContractActivatedNotification;
use App\Notifications\ContractCancelledNotification;
use App\Notifications\ContractCompletedNotification;
use App\Notifications\ContractDisputedNotification;
use App\Notifications\ContractPendingEscrowFreelancerNotification;
use App\Notifications\ContractPendingEscrowClientNotification;
use App\Support\PlatformSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractGenerationService
{
    public function __construct(
        private readonly ContractReferenceGenerator $references,
        private readonly ContractEventLogger $events,
    ) {}

    public function generateFromAward(Quest $quest, QuestOffer $offer, ?Request $request = null): QuestContract
    {
        if (QuestContract::query()->where('quest_offer_id', $offer->id)->exists()) {
            return QuestContract::query()->where('quest_offer_id', $offer->id)->firstOrFail();
        }

        $quest->loadMissing(['client', 'questCategory.parent', 'stateModel', 'localGovernment']);
        $offer->loadMissing(['freelancer']);
        $terms = $offer->award_terms_snapshot ?? [];
        $pricing = is_array($offer->pricing_snapshot) ? $offer->pricing_snapshot : [];

        $totalMinor = (int) ($offer->quoted_amount_minor ?? $terms['price_minor'] ?? 0);
        $platformFeeMinor = (int) ($pricing['platform_fee_minor'] ?? 0);
        $platformFeePercent = PlatformSettings::platformFeePercent();
        if ($platformFeeMinor <= 0 && $totalMinor > 0) {
            $platformFeeMinor = (int) round($totalMinor * ($platformFeePercent / 100));
        }
        $freelancerNetMinor = max(0, $totalMinor - $platformFeeMinor);

        $deliverables = $this->parseDeliverables($offer, $terms);
        $deliveryDate = $terms['deadline_date'] ?? ($offer->planned_finish_date?->toDateString() ?? $offer->proposed_completion_date?->toDateString());
        $graceDays = PlatformSettings::contractAutoReleaseGraceDays();
        $graceHours = PlatformSettings::escrowReleaseCooldownHours();

        $parties = [
            'client' => $this->partySnapshot($quest->client, $terms['client_confirmation'] ?? null),
            'freelancer' => $this->partySnapshot($offer->freelancer, $terms['freelancer_confirmation'] ?? null),
        ];

        $questSnapshot = [
            'quest_id' => $quest->id,
            'reference_code' => $quest->reference_code,
            'title' => $quest->title,
            'category' => $quest->questCategory?->parent?->name
                ? $quest->questCategory->parent->name.' · '.$quest->questCategory->name
                : ($quest->questCategory?->name ?? null),
            'scope_description' => $terms['scope_summary'] ?? strip_tags((string) ($offer->scope_detail ?: $offer->pitch)),
            'location' => collect([
                $quest->stateModel?->name,
                $quest->localGovernment?->name,
                $quest->city,
            ])->filter()->join(', '),
        ];

        $financial = [
            'total_minor' => $totalMinor,
            'total_label' => $this->money($totalMinor),
            'platform_fee_minor' => $platformFeeMinor,
            'platform_fee_label' => $this->money($platformFeeMinor),
            'platform_fee_percent' => $platformFeePercent,
            'freelancer_net_minor' => $freelancerNetMinor,
            'freelancer_net_label' => $this->money($freelancerNetMinor),
            'currency' => 'NGN',
            'pricing_breakdown' => $pricing,
        ];

        $timeline = [
            'agreed_delivery_date' => $deliveryDate,
            'agreed_delivery_label' => $deliveryDate ? \Carbon\Carbon::parse($deliveryDate)->format('j M Y') : null,
            'grace_period_days' => $graceDays,
            'grace_period_hours' => $graceHours,
            'auto_release_plain_english' => __('If you do not mark this job complete or raise a dispute within :days days of the freelancer submitting delivery, funds may release automatically after the :hours-hour review window.', [
                'days' => $graceDays,
                'hours' => $graceHours,
            ]),
            'escrow_funding_deadline_hours' => PlatformSettings::contractEscrowFundingHours(),
        ];

        $revisionPolicy = [
            'revisions_included' => (int) ($terms['revisions_included'] ?? ($offer->corrections_included ? ($offer->corrections_rounds ?: 1) : 0)),
            'revision_definition' => $terms['revision_definition'] ?? $this->defaultRevisionDefinition(),
            'revisions_used' => 0,
        ];

        $platformTerms = [
            'terms_url' => route('legal.terms', absolute: true),
            'clauses' => [
                __('Escrow release requires client confirmation of delivery or expiry of the agreed review window, subject to platform dispute rules.'),
                __('Disputes must be opened within the review window shown on this contract.'),
                __('Off-platform payment is prohibited and may result in account sanctions.'),
                __('Both parties agree to keep quest communications and materials confidential as described in the Terms of Service.'),
                __('Intellectual property transfers to the client upon full escrow release unless otherwise stated in writing within this contract.'),
            ],
        ];

        $signatures = [
            'client' => [
                'name' => $quest->client?->name,
                'action' => $terms['client_confirmation']['action'] ?? __('I agree to the terms of this contract'),
                'confirmed_at' => $terms['client_confirmation']['confirmed_at'] ?? $offer->award_client_confirmed_at?->toIso8601String(),
            ],
            'freelancer' => [
                'name' => $offer->freelancer?->name,
                'action' => $terms['freelancer_confirmation']['action'] ?? __('I agree to the terms of this contract'),
                'confirmed_at' => $terms['freelancer_confirmation']['confirmed_at'] ?? $offer->award_freelancer_confirmed_at?->toIso8601String(),
            ],
            'platform' => [
                'name' => config('app.name', 'HustleSafe'),
                'action' => __('Facilitating party — contract generated automatically'),
                'confirmed_at' => now()->toIso8601String(),
            ],
        ];

        $expiryHours = PlatformSettings::contractEscrowFundingHours();

        return DB::transaction(function () use (
            $quest,
            $offer,
            $parties,
            $questSnapshot,
            $financial,
            $timeline,
            $revisionPolicy,
            $platformTerms,
            $signatures,
            $deliverables,
            $deliveryDate,
            $expiryHours,
            $request,
        ): QuestContract {
            $contract = QuestContract::query()->create([
                'reference_code' => $this->references->next(),
                'quest_id' => $quest->id,
                'quest_offer_id' => $offer->id,
                'client_id' => $quest->client_id,
                'freelancer_id' => $offer->freelancer_id,
                'status' => ContractStatus::PendingEscrow,
                'generated_at' => now(),
                'escrow_expires_at' => now()->addHours($expiryHours),
                'agreed_delivery_date' => $deliveryDate,
                'revisions_included' => $revisionPolicy['revisions_included'],
                'parties_snapshot' => $parties,
                'quest_snapshot' => $questSnapshot,
                'financial_snapshot' => $financial,
                'timeline_snapshot' => $timeline,
                'revision_policy_snapshot' => $revisionPolicy,
                'platform_terms_snapshot' => $platformTerms,
                'signatures_snapshot' => $signatures,
                'current_terms_snapshot' => [
                    'financial' => $financial,
                    'timeline' => $timeline,
                    'quest' => $questSnapshot,
                    'revision_policy' => $revisionPolicy,
                ],
            ]);

            foreach ($deliverables as $index => $item) {
                QuestContractDeliverable::query()->create([
                    'quest_contract_id' => $contract->id,
                    'position' => $index + 1,
                    'title' => $item['title'],
                    'description' => $item['description'] ?? null,
                ]);
            }

            $this->events->log($contract, 'contract.generated', null, [
                'reference' => $contract->reference_code,
            ], $request);

            $quest->client?->notify(new ContractPendingEscrowClientNotification($contract));
            $offer->freelancer?->notify(new ContractPendingEscrowFreelancerNotification($contract));

            return $contract->fresh(['deliverables', 'milestones', 'amendments']);
        });
    }

    /**
     * @return list<array{title: string, description: string|null}>
     */
    private function parseDeliverables(QuestOffer $offer, array $terms): array
    {
        if (! empty($terms['deliverables']) && is_array($terms['deliverables'])) {
            return collect($terms['deliverables'])
                ->map(fn ($row) => [
                    'title' => is_array($row) ? ($row['title'] ?? 'Deliverable') : (string) $row,
                    'description' => is_array($row) ? ($row['description'] ?? null) : null,
                ])
                ->filter(fn ($row) => trim($row['title']) !== '')
                ->values()
                ->all();
        }

        $text = trim(strip_tags((string) ($offer->scope_detail ?: $offer->pitch ?: '')));
        $lines = preg_split('/\r\n|\r|\n|(?:\s*[-•*]\s+)/', $text) ?: [];
        $items = collect($lines)
            ->map(fn ($line) => trim(preg_replace('/^[-•*\d.)]+\s*/', '', $line) ?? ''))
            ->filter(fn ($line) => strlen($line) >= 8)
            ->take(12)
            ->map(fn ($line) => ['title' => str($line)->limit(120)->toString(), 'description' => null])
            ->values()
            ->all();

        if ($items !== []) {
            return $items;
        }

        $materials = is_array($offer->materials) ? $offer->materials : [];
        foreach ($materials as $material) {
            $label = trim((string) ($material['label'] ?? ''));
            if ($label !== '') {
                $items[] = ['title' => $label, 'description' => null];
            }
        }

        if ($items === []) {
            $items[] = [
                'title' => __('Deliver work as described in the accepted proposal and quest brief'),
                'description' => str($text)->limit(500)->toString() ?: null,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>|null  $confirmation
     * @return array<string, mixed>
     */
    private function partySnapshot(?User $user, ?array $confirmation): array
    {
        return [
            'user_id' => $user?->id,
            'full_name' => $user?->name,
            'username' => $user?->username ?? $user?->slug,
            'email' => $user?->email,
            'confirmation_ip' => $confirmation['ip'] ?? null,
            'confirmation_user_agent' => $confirmation['user_agent'] ?? null,
            'confirmed_at' => $confirmation['confirmed_at'] ?? null,
        ];
    }

    private function defaultRevisionDefinition(): string
    {
        return __('A revision adjusts the agreed deliverable within the original scope (e.g. corrections, polish, or minor changes). New features, extra pages, or material scope expansion count as a scope change and require an amendment.');
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 0);
    }
}
