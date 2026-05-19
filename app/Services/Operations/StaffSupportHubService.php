<?php

namespace App\Services\Operations;

use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StaffSupportHubService
{
    public function __construct(private readonly StaffSupportMessagingService $messaging) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public function ticketQueues(): array
    {
        return [
            ['key' => 'my_tickets', 'label' => 'My tickets'],
            ['key' => 'overdue', 'label' => 'Overdue'],
            ['key' => 'escalated', 'label' => 'Escalated to me'],
        ];
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function disputeQueues(): array
    {
        return [
            ['key' => 'tier_1', 'label' => 'Tier 1 · Self-resolution'],
            ['key' => 'tier_2', 'label' => 'Tier 2 · Mediation'],
            ['key' => 'tier_3', 'label' => 'Tier 3 · Admin review'],
            ['key' => 'all_open', 'label' => 'All open disputes'],
        ];
    }

    public function ticketsFor(User $staff, string $queue): array
    {
        if (! $this->messaging->supportTablesReady()) {
            return ['items' => [], 'meta' => ['total' => 0]];
        }

        $query = SupportTicket::query()
            ->with(['user:id,name,email', 'assignedAdmin:id,name,email'])
            ->where(fn ($scope) => $scope
                ->where('assigned_admin_id', $staff->id)
                ->orWhere('opened_by_admin_id', $staff->id));

        match ($queue) {
            'overdue' => $query->whereNotIn('status', ['resolved', 'closed'])
                ->where('due_at', '<', now()),
            'escalated' => $query->where('priority', 'critical')->whereNotIn('status', ['resolved', 'closed']),
            default => null,
        };

        $rows = $query->latest('updated_at')->limit(250)->get();

        return [
            'items' => $rows->map(fn (SupportTicket $ticket) => [
                'id' => $ticket->id,
                'subject' => $ticket->subject,
                'status' => $ticket->status,
                'priority' => $ticket->priority,
                'category' => $ticket->category,
                'user' => $ticket->user?->only(['id', 'name', 'email']),
                'due_at' => $ticket->due_at?->toIso8601String(),
                'updated_at' => $ticket->updated_at?->toIso8601String(),
                'age_hours' => $ticket->created_at ? $ticket->created_at->diffInHours(now()) : null,
            ])->values()->all(),
            'meta' => ['total' => $rows->count()],
        ];
    }

    public function chatsWaiting(User $staff): array
    {
        return [
            'items' => $this->messaging->assignedChats($staff)->values()->all(),
            'meta' => ['total' => $this->messaging->assignedChats($staff)->count()],
        ];
    }

    public function disputes(string $queue): array
    {
        if (! Schema::hasTable('quest_disputes')) {
            return ['items' => [], 'meta' => ['total' => 0]];
        }

        $query = \App\Models\QuestDispute::query()
            ->with(['quest:id,title,reference_code', 'openedBy:id,name,email'])
            ->whereNotIn('status', ['resolved', 'closed', 'cancelled']);

        match ($queue) {
            'tier_1' => $query->where('tier', 1),
            'tier_2' => $query->where('tier', 2),
            'tier_3' => $query->where('tier', '>=', 3),
            default => null,
        };

        $rows = $query->latest('updated_at')->limit(250)->get();

        return [
            'items' => $rows->map(fn ($dispute) => [
                'id' => $dispute->id,
                'uuid' => $dispute->uuid,
                'status' => $dispute->status,
                'tier' => $dispute->tier,
                'quest' => $dispute->quest?->only(['id', 'title', 'reference_code']),
                'opened_by' => $dispute->openedBy?->only(['id', 'name', 'email']),
                'updated_at' => $dispute->updated_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => ['total' => $rows->count()],
        ];
    }

    public function globalSearch(Request $request): array
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return ['results' => [], 'message' => 'Enter at least 2 characters to search.'];
        }

        $results = collect();

        Quest::query()
            ->where(fn ($scope) => $scope
                ->where('title', 'like', "%{$q}%")
                ->orWhere('reference_code', 'like', "%{$q}%")
                ->orWhere('id', is_numeric($q) ? (int) $q : 0)
                ->orWhere('description', 'like', "%{$q}%"))
            ->limit(15)
            ->get(['id', 'title', 'reference_code', 'admin_status', 'status'])
            ->each(fn (Quest $quest) => $results->push([
                'type' => 'quest',
                'id' => $quest->id,
                'label' => $quest->title,
                'meta' => $quest->reference_code.' · '.$quest->status?->value ?? (string) $quest->status,
            ]));

        QuestOffer::query()
            ->where(fn ($scope) => $scope
                ->where('id', is_numeric($q) ? (int) $q : 0)
                ->orWhere('pitch', 'like', "%{$q}%")
                ->orWhere('scope_detail', 'like', "%{$q}%"))
            ->limit(15)
            ->get(['id', 'quest_id', 'admin_status', 'status'])
            ->each(fn (QuestOffer $offer) => $results->push([
                'type' => 'proposal',
                'id' => $offer->id,
                'label' => 'Proposal #'.$offer->id,
                'meta' => (string) ($offer->admin_status?->value ?? $offer->admin_status),
            ]));

        User::query()
            ->where(fn ($scope) => $scope
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('username', 'like', "%{$q}%"))
            ->limit(15)
            ->get(['id', 'name', 'email', 'phone'])
            ->each(fn (User $user) => $results->push([
                'type' => 'user',
                'id' => $user->id,
                'label' => $user->name,
                'meta' => $user->email,
            ]));

        if ($this->messaging->supportTablesReady()) {
            SupportTicket::query()
                ->where(fn ($scope) => $scope
                    ->where('id', is_numeric($q) ? (int) $q : 0)
                    ->orWhere('subject', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%"))
                ->limit(10)
                ->get(['id', 'subject', 'status'])
                ->each(fn (SupportTicket $ticket) => $results->push([
                    'type' => 'ticket',
                    'id' => $ticket->id,
                    'label' => $ticket->subject,
                    'meta' => $ticket->status,
                ]));
        }

        return ['results' => $results->values()->all(), 'message' => null];
    }
}
