<?php

namespace App\Services\Admin;

use App\Models\QuestCompletionEvent;
use App\Support\NgnMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AdminQuestCompletionEventsService
{
    /**
     * @return array{events: LengthAwarePaginator, filters: array<string, mixed>, event_types: list<string>}
     */
    public function index(Request $request): array
    {
        $perPage = min(100, max(15, $request->integer('per_page', 25)));
        $sort = (string) $request->input('sort', 'occurred_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = QuestCompletionEvent::query()
            ->with([
                'quest:id,title,slug,uuid,client_id,freelancer_id,escrow_status,status',
                'quest.client:id,name,email',
                'quest.freelancer:id,name,email',
                'actor:id,name,email',
            ]);

        if ($request->filled('event_type')) {
            $query->where('event_type', $request->input('event_type'));
        }

        if ($request->filled('q')) {
            $q = '%'.trim((string) $request->input('q')).'%';
            $query->where(function (Builder $sub) use ($q): void {
                $sub->whereHas('quest', fn (Builder $quest) => $quest->where('title', 'like', $q)->orWhere('slug', 'like', $q))
                    ->orWhereHas('actor', fn (Builder $actor) => $actor->where('email', 'like', $q)->orWhere('name', 'like', $q))
                    ->orWhere('event_type', 'like', $q);
            });
        }

        if ($request->filled('from')) {
            $query->where('occurred_at', '>=', $request->date('from')->startOfDay());
        }

        if ($request->filled('to')) {
            $query->where('occurred_at', '<=', $request->date('to')->endOfDay());
        }

        $sortColumn = match ($sort) {
            'event_type' => 'event_type',
            'quest' => 'quest_id',
            'actor' => 'actor_user_id',
            default => 'occurred_at',
        };

        $events = $query->orderBy($sortColumn, $direction)->paginate($perPage)->withQueryString();

        $events->getCollection()->transform(function (QuestCompletionEvent $event): array {
            $quest = $event->quest;

            return [
                'id' => $event->id,
                'event_type' => $event->event_type,
                'event_label' => str_replace('_', ' ', $event->event_type),
                'occurred_at' => $event->occurred_at?->timezone(config('app.timezone'))->toIso8601String(),
                'ip_address' => $event->ip_address,
                'actor' => $event->actor ? [
                    'name' => $event->actor->name,
                    'email' => $event->actor->email,
                ] : ['name' => 'System', 'email' => null],
                'quest' => $quest ? [
                    'id' => $quest->id,
                    'title' => $quest->title,
                    'route_key' => $quest->getRouteKey(),
                    'status' => $quest->status?->value ?? (string) $quest->status,
                    'escrow_status' => $quest->escrow_status,
                ] : null,
                'meta' => $event->meta ?? [],
                'amount_minor' => isset($event->meta['amount_minor']) ? NgnMoney::format((int) $event->meta['amount_minor']) : null,
            ];
        });

        return [
            'events' => $events,
            'filters' => [
                'q' => $request->input('q', ''),
                'event_type' => $request->input('event_type', ''),
                'from' => $request->input('from'),
                'to' => $request->input('to'),
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'event_types' => QuestCompletionEvent::query()->distinct()->orderBy('event_type')->pluck('event_type')->all(),
        ];
    }

    /**
     * @return list<array{label: string, at: ?string, actor: string, detail: ?string}>
     */
    public function questTimeline(int $questId): array
    {
        return QuestCompletionEvent::query()
            ->where('quest_id', $questId)
            ->with('actor:id,name,email')
            ->orderBy('occurred_at')
            ->get()
            ->map(fn (QuestCompletionEvent $event) => [
                'label' => str_replace('_', ' ', $event->event_type),
                'at' => $event->occurred_at?->timezone(config('app.timezone'))->toIso8601String(),
                'actor' => $event->actor?->email ?? 'System',
                'detail' => is_array($event->meta) ? json_encode($event->meta, JSON_UNESCAPED_UNICODE) : null,
            ])
            ->all();
    }
}
