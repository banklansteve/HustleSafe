<?php

namespace App\Services\Admin;

use App\Events\AdminActivityFeedEventCreated;
use App\Models\AdminActivityFeedEvent;
use App\Models\QuestDispute;
use App\Models\Quest;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class AdminActivityFeedService
{
    /**
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, mixed> $metadata
     */
    public function record(
        string $category,
        string $eventKey,
        string $title,
        string $summary,
        array $entities = [],
        array $metadata = [],
        ?int $amountMinor = null,
        ?User $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        ?int $stateId = null,
        ?int $localGovernmentId = null,
        ?int $questCategoryId = null,
        ?string $severity = null,
        mixed $occurredAt = null,
    ): AdminActivityFeedEvent {
        $event = AdminActivityFeedEvent::query()->create([
            'category' => $category,
            'event_key' => $eventKey,
            'severity' => $severity ?? $this->inferSeverity($category, $eventKey, $amountMinor),
            'title' => $title,
            'summary' => $summary,
            'entities' => $entities,
            'metadata' => $metadata,
            'amount_minor' => $amountMinor,
            'actor_user_id' => $actor?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'state_id' => $stateId,
            'local_government_id' => $localGovernmentId,
            'quest_category_id' => $questCategoryId,
            'occurred_at' => $occurredAt ? Carbon::parse($occurredAt) : now(),
        ]);

        broadcast(new AdminActivityFeedEventCreated($event))->toOthers();

        return $event;
    }

    public function paginate(array $filters = [], int $perPage = 50): LengthAwarePaginator
    {
        $query = AdminActivityFeedEvent::query()
            ->with(['state:id,name', 'localGovernment:id,name', 'categoryModel:id,name'])
            ->latest('occurred_at');

        $this->applyFilters($query, $filters);

        $paginator = $query->paginate(min(200, max(1, $perPage)));
        $paginator->setCollection($paginator->getCollection()->map(fn (AdminActivityFeedEvent $event) => $this->serialize($event)));

        return $paginator;
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $today = now()->startOfDay();

        return [
            'events_24h' => AdminActivityFeedEvent::query()->where('occurred_at', '>=', now()->subDay())->count(),
            'open_disputes' => QuestDispute::query()->whereNull('resolved_at')->count(),
            'transactions_today' => [
                'count' => AdminActivityFeedEvent::query()
                    ->where('category', 'financial')
                    ->where('occurred_at', '>=', $today)
                    ->count(),
                'value' => AdminActivityFeedEvent::query()
                    ->where('category', 'financial')
                    ->where('occurred_at', '>=', $today)
                    ->sum('amount_minor'),
            ],
            'new_signups_today' => User::query()->where('created_at', '>=', $today)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function widgetPayload(int $limit = 8): array
    {
        return [
            'summary' => $this->summary(),
            'events' => AdminActivityFeedEvent::query()
                ->with(['state:id,name', 'localGovernment:id,name', 'categoryModel:id,name'])
                ->latest('occurred_at')
                ->limit($limit)
                ->get()
                ->map(fn (AdminActivityFeedEvent $event) => $this->serialize($event))
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(AdminActivityFeedEvent $event): array
    {
        return [
            'id' => $event->id,
            'uuid' => $event->uuid,
            'category' => $event->category,
            'event_key' => $event->event_key,
            'severity' => $event->severity,
            'title' => $event->title,
            'summary' => $event->summary,
            'entities' => $event->entities ?? [],
            'metadata' => [
                ...($event->metadata ?? []),
                'amount' => $event->amount_minor ? '₦'.number_format($event->amount_minor / 100, 2) : null,
                'state' => $event->state?->name,
                'local_government' => $event->localGovernment?->name,
                'category' => $event->categoryModel?->name,
            ],
            'amount_minor' => $event->amount_minor,
            'occurred_at' => $event->occurred_at?->toIso8601String(),
            'occurred_at_label' => $event->occurred_at?->diffForHumans(),
            'actions' => $this->actionsFor($event),
        ];
    }

    public function seedRecentFromExistingData(int $limit = 80): void
    {
        if (AdminActivityFeedEvent::query()->exists()) {
            return;
        }

        Quest::query()
            ->with(['client', 'questCategory', 'stateModel'])
            ->latest()
            ->limit($limit)
            ->get()
            ->each(function (Quest $quest): void {
                $this->record(
                    'jobs',
                    'quest.posted',
                    'New quest posted',
                    "{$quest->client?->name} posted {$quest->title}",
                    $this->entities([
                        ['type' => 'user', 'id' => $quest->client_id, 'label' => $quest->client?->name],
                        ['type' => 'quest', 'id' => $quest->id, 'label' => $quest->title],
                    ]),
                    ['budget' => $quest->budget_amount_minor ? '₦'.number_format($quest->budget_amount_minor / 100, 2) : null],
                    $quest->budget_amount_minor,
                    $quest->client,
                    Quest::class,
                    $quest->id,
                    $quest->state_id,
                    $quest->local_government_id,
                    $quest->quest_category_id,
                    'info',
                    $quest->created_at,
                );
            });
    }

    /**
     * @param array<int, array{type: string, id: mixed, label: mixed}> $items
     * @return array<int, array<string, mixed>>
     */
    public function entities(array $items): array
    {
        return collect($items)
            ->filter(fn ($item) => ! empty($item['id']) && ! empty($item['label']))
            ->map(fn ($item) => [
                'type' => $item['type'],
                'id' => $item['id'],
                'label' => (string) $item['label'],
                'href' => $this->entityHref((string) $item['type'], (int) $item['id']),
            ])
            ->values()
            ->all();
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        $category = (string) ($filters['category'] ?? 'all');
        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $like = '%'.str_replace('%', '', $search).'%';
                $q->where('title', 'like', $like)
                    ->orWhere('summary', 'like', $like)
                    ->orWhere('metadata', 'like', $like)
                    ->orWhere('entities', 'like', $like);
            });
        }
    }

    private function actionsFor(AdminActivityFeedEvent $event): array
    {
        return match ($event->event_key) {
            'dispute.raised' => [
                ['key' => 'view_dispute', 'label' => 'View dispute', 'method' => 'open'],
                ['key' => 'assign_to_me', 'label' => 'Assign to me', 'method' => 'post', 'enabled' => true],
                ['key' => 'flag_urgent', 'label' => 'Flag as urgent', 'method' => 'post', 'enabled' => true],
            ],
            'security.fraud_flag', 'security.alert' => [
                ['key' => 'view_user', 'label' => 'View user profile', 'method' => 'open'],
                ['key' => 'suspend_account', 'label' => 'Suspend account', 'method' => 'post', 'enabled' => true],
                ['key' => 'dismiss_flag', 'label' => 'Dismiss flag', 'method' => 'post', 'enabled' => false],
            ],
            'review.low_rating' => [
                ['key' => 'view_review', 'label' => 'View review', 'method' => 'open'],
                ['key' => 'flag_moderation', 'label' => 'Flag for moderation', 'method' => 'post', 'enabled' => false],
                ['key' => 'view_contract', 'label' => 'View contract', 'method' => 'open'],
            ],
            'financial.payout_failed' => [
                ['key' => 'view_transaction', 'label' => 'View transaction', 'method' => 'open'],
                ['key' => 'retry_payout', 'label' => 'Retry payout', 'method' => 'post', 'enabled' => false],
                ['key' => 'contact_freelancer', 'label' => 'Contact freelancer', 'method' => 'open'],
            ],
            default => [
                ['key' => 'inspect', 'label' => 'Inspect', 'method' => 'open'],
            ],
        };
    }

    private function inferSeverity(string $category, string $eventKey, ?int $amountMinor): string
    {
        if ($category === 'security' || str_contains($eventKey, 'fraud')) {
            return 'critical';
        }

        if ($category === 'disputes' && ($amountMinor ?? 0) >= 20000000) {
            return 'critical';
        }

        if (in_array($category, ['disputes', 'security'], true)) {
            return 'warning';
        }

        return 'info';
    }

    private function entityHref(string $type, int $id): ?string
    {
        return match ($type) {
            'user' => route('admin.management.show', ['resource' => 'users', 'record' => $id], false),
            'quest' => route('admin.management.show', ['resource' => 'quests', 'record' => $id], false),
            'dispute' => route('admin.management.show', ['resource' => 'quest_disputes', 'record' => $id], false),
            'review' => route('admin.management.show', ['resource' => 'reviews', 'record' => $id], false),
            default => null,
        };
    }
}
