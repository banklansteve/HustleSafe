<?php

namespace App\Services\Operations;

use App\Models\Quest;
use App\Models\StaffEscrowAnomalyNote;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Support\Collection;

class StaffEscrowAnomalyService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(): array
    {
        $anomalies = collect()
            ->merge($this->fundedNoStart())
            ->merge($this->clientReviewStale())
            ->merge($this->longFrozen())
            ->merge($this->overdueMilestonesProxy())
            ->sortByDesc('severity_score')
            ->values();

        $notes = StaffEscrowAnomalyNote::query()
            ->with('staff:id,name')
            ->whereIn('quest_id', $anomalies->pluck('quest_id'))
            ->latest()
            ->get()
            ->groupBy('quest_id');

        return [
            'items' => $anomalies->map(function (array $row) use ($notes) {
                $questNotes = $notes->get($row['quest_id'], collect());

                return [
                    ...$row,
                    'notes' => $questNotes->take(3)->map(fn (StaffEscrowAnomalyNote $n) => [
                        'id' => $n->id,
                        'status' => $n->status,
                        'summary' => $n->outreach_summary,
                        'staff' => $n->staff?->name,
                        'created_at' => $n->created_at?->toIso8601String(),
                    ]),
                ];
            })->all(),
        ];
    }

    public function detail(Quest $quest): array
    {
        $quest->load(['client:id,name,email', 'freelancer:id,name,email', 'category:id,name']);

        $notes = StaffEscrowAnomalyNote::query()
            ->with('staff:id,name')
            ->where('quest_id', $quest->id)
            ->latest()
            ->get();

        return [
            'quest' => [
                'id' => $quest->id,
                'uuid' => $quest->uuid,
                'title' => $quest->title,
                'reference_code' => $quest->reference_code,
                'status' => $quest->status?->value ?? $quest->status,
                'escrow_status' => $quest->escrow_status,
                'budget_minor' => $quest->budget_amount_minor,
                'scheduled_start_date' => $quest->scheduled_start_date?->toDateString(),
                'escrow_funded_at' => $quest->escrow_funded_at?->toIso8601String(),
                'delivered_at' => $quest->delivered_at?->toIso8601String(),
                'escrow_frozen_at' => $quest->escrow_frozen_at?->toIso8601String(),
                'client' => $quest->client?->only(['id', 'name', 'email']),
                'freelancer' => $quest->freelancer?->only(['id', 'name', 'email']),
                'category' => $quest->category?->name,
            ],
            'notes' => $notes->map(fn (StaffEscrowAnomalyNote $n) => [
                'id' => $n->id,
                'anomaly_type' => $n->anomaly_type,
                'status' => $n->status,
                'outreach_summary' => $n->outreach_summary,
                'staff' => $n->staff?->name,
                'created_at' => $n->created_at?->toIso8601String(),
            ]),
        ];
    }

    public function recordOutreach(Quest $quest, User $staff, array $data): StaffEscrowAnomalyNote
    {
        $note = StaffEscrowAnomalyNote::query()->create([
            'quest_id' => $quest->id,
            'anomaly_type' => $data['anomaly_type'],
            'staff_user_id' => $staff->id,
            'status' => $data['status'] ?? 'open',
            'outreach_summary' => $data['outreach_summary'],
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->logger->log($staff, 'staff_escrow_anomaly.outreach', StaffEscrowAnomalyNote::class, $note->id, [
            'quest_id' => $quest->id,
        ]);

        return $note;
    }

    public function resolveNote(StaffEscrowAnomalyNote $note, User $staff): void
    {
        $note->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
        ])->save();

        $this->logger->log($staff, 'staff_escrow_anomaly.resolved', StaffEscrowAnomalyNote::class, $note->id, []);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fundedNoStart(): Collection
    {
        $days = config('operations_extended.escrow_no_start_work_days', 3);

        return Quest::query()
            ->with(['client:id,name', 'freelancer:id,name'])
            ->whereNotNull('escrow_funded_at')
            ->whereNull('delivered_at')
            ->where(function ($q): void {
                $q->whereNull('status')->orWhereNotIn('status', ['completed', 'cancelled']);
            })
            ->whereDate('scheduled_start_date', '<=', now()->subDays($days))
            ->whereNull('freelancer_id')
            ->limit(40)
            ->get()
            ->map(fn (Quest $q) => $this->row($q, 'funded_no_start', 'Client funded escrow but work has not started past the agreed start date.', 80));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function clientReviewStale(): Collection
    {
        $days = config('operations_extended.escrow_client_review_stale_days', 7);

        return Quest::query()
            ->with(['client:id,name', 'freelancer:id,name'])
            ->whereNotNull('delivered_at')
            ->where('delivered_at', '<=', now()->subDays($days))
            ->where(function ($q): void {
                $q->whereNull('escrow_status')->orWhereNotIn('escrow_status', ['released', 'refunded']);
            })
            ->limit(40)
            ->get()
            ->map(fn (Quest $q) => $this->row($q, 'client_review_stale', 'Freelancer delivered but client approval is unusually delayed.', 70));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function longFrozen(): Collection
    {
        $days = config('operations_extended.escrow_max_frozen_days', 14);

        return Quest::query()
            ->with(['client:id,name', 'freelancer:id,name'])
            ->whereNotNull('escrow_frozen_at')
            ->where('escrow_frozen_at', '<=', now()->subDays($days))
            ->limit(40)
            ->get()
            ->map(fn (Quest $q) => $this->row($q, 'long_frozen', 'Escrow frozen longer than the configured maximum without resolution.', 90));
    }

    /**
     * Proxy: active in-progress quests past expected delivery with funded escrow.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function overdueMilestonesProxy(): Collection
    {
        $days = config('operations_extended.escrow_milestone_overdue_days', 5);

        return Quest::query()
            ->with(['client:id,name', 'freelancer:id,name'])
            ->whereNotNull('escrow_funded_at')
            ->whereNull('delivered_at')
            ->whereNotNull('expected_delivery_date')
            ->whereDate('expected_delivery_date', '<=', now()->subDays($days))
            ->limit(40)
            ->get()
            ->map(fn (Quest $q) => $this->row($q, 'milestone_overdue', 'Milestone or delivery window is significantly overdue with no delivery logged.', 75));
    }

    private function row(Quest $quest, string $type, string $summary, int $severity): array
    {
        return [
            'quest_id' => $quest->id,
            'quest_uuid' => $quest->uuid,
            'title' => $quest->title,
            'reference_code' => $quest->reference_code,
            'anomaly_type' => $type,
            'anomaly_label' => str_replace('_', ' ', ucfirst($type)),
            'summary' => $summary,
            'escrow_status' => $quest->escrow_status,
            'client' => $quest->client?->name,
            'freelancer' => $quest->freelancer?->name,
            'severity_score' => $severity,
        ];
    }
}
