<?php

namespace App\Services\Operations;

use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Models\QuestOffer;
use App\Models\StaffPatrolItem;
use App\Models\StaffPatrolSession;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffContentPatrolService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function sessions(User $staff): array
    {
        $sessions = StaffPatrolSession::query()
            ->where('staff_user_id', $staff->id)
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (StaffPatrolSession $session) => $this->sessionRow($session));

        return ['items' => $sessions];
    }

    public function startSession(User $staff, array $data): StaffPatrolSession
    {
        $session = StaffPatrolSession::query()->create([
            'staff_user_id' => $staff->id,
            'content_type' => $data['content_type'],
            'category_id' => $data['category_id'] ?? null,
            'date_from' => $data['date_from'] ?? null,
            'date_to' => $data['date_to'] ?? null,
            'sample_size' => min(100, max(5, (int) ($data['sample_size'] ?? 25))),
            'status' => 'active',
        ]);

        $this->populateItems($session);

        return $session->fresh('items');
    }

    public function sessionDetail(StaffPatrolSession $session, User $staff): array
    {
        abort_unless((int) $session->staff_user_id === (int) $staff->id, 403);
        $session->load('items');

        $current = $session->items->firstWhere('reviewed_at', null);

        return [
            'session' => $this->sessionRow($session),
            'progress_percent' => $session->sample_size > 0
                ? round(($session->reviewed_count / $session->sample_size) * 100, 1)
                : 0,
            'current_item' => $current ? $this->itemRow($current) : null,
            'items' => $session->items->map(fn (StaffPatrolItem $item) => $this->itemRow($item)),
        ];
    }

    public function decide(StaffPatrolItem $item, User $staff, array $data, Request $request): void
    {
        $item->load('session');
        abort_unless((int) $item->session?->staff_user_id === (int) $staff->id, 403);

        $item->forceFill([
            'decision' => $data['decision'],
            'notes' => $data['notes'] ?? null,
            'reviewed_at' => now(),
        ])->save();

        $session = $item->session;
        $session->forceFill([
            'reviewed_count' => $session->items()->whereNotNull('reviewed_at')->count(),
        ])->save();

        if ($session->reviewed_count >= $session->sample_size) {
            $session->forceFill(['status' => 'completed', 'completed_at' => now()])->save();
        }

        match ($data['decision']) {
            'flag' => $this->flagContent($item, $staff, $data, $request),
            'escalate' => $this->escalate($item, $staff, $data, $request),
            'contact' => null,
            default => null,
        };

        $this->logger->log($staff, 'operations.patrol.'.$data['decision'], StaffPatrolItem::class, $item->id, $data, $request);
    }

    public function categories(): array
    {
        return [
            'categories' => QuestCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]),
        ];
    }

    private function populateItems(StaffPatrolSession $session): void
    {
        $query = match ($session->content_type) {
            'proposals' => QuestOffer::query()->with(['quest:id,title,reference_code', 'freelancer:id,name,email']),
            default => Quest::query()->with(['client:id,name,email', 'category:id,name']),
        };

        if ($session->category_id) {
            if ($session->content_type === 'proposals') {
                $query->whereHas('quest', fn ($q) => $q->where('quest_category_id', $session->category_id));
            } else {
                $query->where('quest_category_id', $session->category_id);
            }
        }

        if ($session->date_from) {
            $query->whereDate('created_at', '>=', $session->date_from);
        }
        if ($session->date_to) {
            $query->whereDate('created_at', '<=', $session->date_to);
        }

        $records = $query->inRandomOrder()->limit($session->sample_size)->get();

        foreach ($records as $record) {
            StaffPatrolItem::query()->create([
                'staff_patrol_session_id' => $session->id,
                'reviewable_type' => $record::class,
                'reviewable_id' => $record->id,
                'risk_signals' => $this->riskSignals($record),
            ]);
        }
    }

    private function riskSignals(object $record): array
    {
        if ($record instanceof Quest) {
            return array_filter([
                $record->admin_status ? 'admin_status:'.($record->admin_status->value ?? $record->admin_status) : null,
                $record->escrow_status ? 'escrow:'.$record->escrow_status : null,
            ]);
        }

        if ($record instanceof QuestOffer) {
            return array_filter([
                $record->admin_status ? 'proposal_admin:'.$record->admin_status->value : null,
            ]);
        }

        return [];
    }

    private function flagContent(StaffPatrolItem $item, User $staff, array $data, Request $request): void
    {
        // Patrol flag is logged; dedicated moderation flags can be added from moderation centre.
    }

    private function escalate(StaffPatrolItem $item, User $staff, array $data, Request $request): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            return;
        }

        $super = User::query()->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))->first();
        if ($super === null) {
            throw ValidationException::withMessages(['escalate' => 'No Super Admin available.']);
        }

        AdminTask::query()->create([
            'created_by_admin_id' => $staff->id,
            'assigned_to_admin_id' => $super->id,
            'source_type' => $item->reviewable_type,
            'source_id' => $item->reviewable_id,
            'title' => 'Patrol escalation · '.class_basename($item->reviewable_type).' #'.$item->reviewable_id,
            'description' => $data['notes'] ?? 'Escalated from content quality patrol.',
            'priority' => 'high',
            'status' => 'todo',
            'due_at' => now()->addDay(),
        ]);
    }

    private function sessionRow(StaffPatrolSession $session): array
    {
        return [
            'id' => $session->id,
            'content_type' => $session->content_type,
            'category_id' => $session->category_id,
            'sample_size' => $session->sample_size,
            'reviewed_count' => $session->reviewed_count,
            'status' => $session->status,
            'completed_at' => $session->completed_at?->toIso8601String(),
            'created_at' => $session->created_at?->toIso8601String(),
        ];
    }

    private function itemRow(StaffPatrolItem $item): array
    {
        $item->loadMissing('reviewable');
        $reviewable = $item->reviewable;
        $title = 'Content #'.$item->reviewable_id;
        $excerpt = null;

        if ($reviewable instanceof Quest) {
            $title = $reviewable->title;
            $excerpt = str($reviewable->description)->limit(200)->toString();
        } elseif ($reviewable instanceof QuestOffer) {
            $title = 'Proposal on '.$reviewable->quest?->title;
            $excerpt = str($reviewable->pitch)->limit(200)->toString();
        }

        return [
            'id' => $item->id,
            'reviewable_type' => class_basename($item->reviewable_type),
            'reviewable_id' => $item->reviewable_id,
            'title' => $title,
            'excerpt' => $excerpt,
            'risk_signals' => $item->risk_signals ?? [],
            'decision' => $item->decision,
            'reviewed_at' => $item->reviewed_at?->toIso8601String(),
        ];
    }
}
