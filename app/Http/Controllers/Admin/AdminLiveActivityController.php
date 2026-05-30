<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivityFeedEvent;
use App\Models\QuestDispute;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Admin\AdminActivityFeedService;
use App\Services\Admin\AdminManagementService;
use App\Services\Support\SupportTicketManagementService;
use App\Support\Admin\AdminManagementRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminLiveActivityController extends Controller
{
    public function __construct(
        private AdminActivityFeedService $feed,
        private AdminManagementService $management,
        private SupportTicketManagementService $supportTickets,
    ) {}

    public function index(Request $request): Response
    {
        $this->feed->seedRecentFromExistingData();
        $isSuperAdmin = $request->user()?->role?->slug === 'super_admin';

        return Inertia::render('Admin/LiveActivity/Index', [
            'initial_events' => $this->feed->paginate([], 200),
            'summary' => $this->feed->summary(),
            'initial_support_tickets' => $isSuperAdmin
                ? $this->supportTickets->liveFeedPaginate($request->user(), [], 50)
                : null,
            'assignable_admins' => $isSuperAdmin
                ? collect($this->supportTickets->assignableAdmins())->map->only(['id', 'name', 'email'])->values()
                : [],
            'ticket_statuses' => SupportTicketManagementService::STATUSES,
            'current_user_id' => $request->user()?->id,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        return response()->json($this->feed->paginate($request->only(['category', 'search']), (int) $request->input('per_page', 50)));
    }

    public function summary(): JsonResponse
    {
        return response()->json($this->feed->summary());
    }

    public function widget(): JsonResponse
    {
        return response()->json($this->feed->widgetPayload());
    }

    public function supportTickets(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $filters = $request->only(['status', 'search']);
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $filters['search'] = $search;
        }

        return response()->json(
            $this->supportTickets->liveFeedPaginate(
                $request->user(),
                $filters,
                (int) $request->input('per_page', 50),
            ),
        );
    }

    public function supportTicket(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json([
            'ticket' => $this->supportTickets->ticketDetail($ticket, $request->user()),
            'assignable_admins' => collect($this->supportTickets->assignableAdmins())->map->only(['id', 'name', 'email'])->values(),
            'statuses' => SupportTicketManagementService::STATUSES,
        ]);
    }

    public function entity(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string', 'max:40'],
            'id' => ['required', 'integer'],
        ]);
        $resource = match ($data['type']) {
            'user' => 'users',
            'quest' => 'quests',
            'dispute' => 'quest_disputes',
            'review' => 'reviews',
            default => null,
        };

        abort_if($resource === null, 404);

        $definition = AdminManagementRegistry::resource($resource);
        $model = AdminManagementRegistry::modelClass($resource)::query()->findOrFail((int) $data['id']);

        return response()->json([
            'resource' => $resource,
            'label' => $definition['label'] ?? str($resource)->headline()->toString(),
            'columns' => collect(array_keys($model->getAttributes()))
                ->reject(fn (string $column) => in_array($column, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true))
                ->values()
                ->all(),
            'record' => $this->management->serializeDetailRow($resource, $model),
            'href' => route('admin.management.show', ['resource' => $resource, 'record' => $model->getKey()]),
        ]);
    }

    public function action(Request $request, AdminActivityFeedEvent $event): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'string', 'max:80'],
        ]);

        match ($data['action']) {
            'assign_to_me' => $this->assignDispute($event, $request),
            'flag_urgent' => $this->flagUrgent($event, $request),
            'suspend_account' => $this->suspendSubjectUser($event, $request),
            default => null,
        };

        return response()->json([
            'message' => 'Action processed.',
            'event' => $this->feed->serialize($event->refresh()),
        ]);
    }

    private function assignDispute(AdminActivityFeedEvent $event, Request $request): void
    {
        if ($event->subject_type !== QuestDispute::class || ! $event->subject_id) {
            return;
        }

        QuestDispute::query()
            ->whereKey($event->subject_id)
            ->update(['awaiting_user_id' => $request->user()?->id]);

        $event->forceFill([
            'metadata' => [
                ...($event->metadata ?? []),
                'assigned_to' => $request->user()?->name,
                'assigned_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function flagUrgent(AdminActivityFeedEvent $event, Request $request): void
    {
        $event->forceFill([
            'severity' => 'critical',
            'metadata' => [
                ...($event->metadata ?? []),
                'urgent' => true,
                'flagged_by' => $request->user()?->name,
                'flagged_at' => now()->toIso8601String(),
            ],
        ])->save();
    }

    private function suspendSubjectUser(AdminActivityFeedEvent $event, Request $request): void
    {
        $userId = collect($event->entities ?? [])->firstWhere('type', 'user')['id'] ?? null;

        if (! $userId) {
            return;
        }

        User::query()
            ->whereKey($userId)
            ->update(['suspended_at' => now()]);

        $event->forceFill([
            'metadata' => [
                ...($event->metadata ?? []),
                'suspended_by' => $request->user()?->name,
                'suspended_at' => now()->toIso8601String(),
            ],
        ])->save();
    }
}
