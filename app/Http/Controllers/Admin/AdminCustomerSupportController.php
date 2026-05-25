<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReassignCustomerSupportChatRequest;
use App\Http\Requests\Support\SendCustomerSupportMessageRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use App\Services\Chat\GifSearchService;
use App\Services\Support\CustomerSupportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminCustomerSupportController extends Controller
{
    public function __construct(
        private readonly CustomerSupportService $service,
        private readonly GifSearchService $gifSearch,
    ) {}

    public function index(Request $request): Response
    {
        return $this->workspace($request, 'admin');
    }

    public function operationsIndex(Request $request): Response
    {
        return $this->workspace($request, 'operations');
    }

    private function workspace(Request $request, string $routeNamespace): Response
    {
        $admin = $request->user();
        $this->processUnassignedQueue();

        $ticketRef = trim((string) $request->query('ticket', ''));
        $selected = $ticketRef !== ''
            ? SupportTicket::query()->where('uuid', $ticketRef)->first()
            : null;

        if ($selected && ! $this->service->canAccessTicket($selected, $admin)) {
            $selected = null;
        }

        if ($selected) {
            $selected->load([
                'customer:id,name,email,username,current_verification_level,verification_tier',
                'assignedAdmin:id,name',
            ]);
        }

        $panels = $this->service->queuePanelsForAdmin(
            $admin,
            $request->query('q'),
            $request->query('section'),
            $request->integer('admin_id') ?: null,
        );

        return Inertia::render('Admin/CustomerSupport/Workspace', [
            'routeNamespace' => $routeNamespace,
            'queuePanels' => $panels,
            'selectedTicket' => $selected ? $this->service->ticketDetailPayload($selected, $admin, 0) : null,
            'messages' => [],
            'hasMore' => false,
            'userContext' => null,
            'onlineAdmins' => $this->onlineAdminsPayload(),
            'allAdmins' => $this->reassignTargetsPayload($admin),
            'filterAdmins' => $this->staffAdminsPayload(),
            'isSuperAdmin' => $admin->role?->slug === 'super_admin',
            'categories' => $this->service->categories(),
            'messageTemplates' => $this->service->messageTemplatesPayload($admin),
            'viewerId' => $admin->id,
        ]);
    }

    public function performance(Request $request): Response
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Admin/CustomerSupport/Performance', [
            'metrics' => $this->service->performanceMetrics(),
            'surveySteps' => $this->service->feedbackSurveySteps(),
        ]);
    }

    public function performanceFeedback(Request $request, User $admin): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        abort_unless($admin->role?->slug === 'admin', 404);

        return response()->json($this->service->adminPerformanceFeedbackDetail((int) $admin->id));
    }

    public function queue(Request $request): JsonResponse
    {
        return response()->json($this->service->queuePanelsForAdmin(
            $request->user(),
            $request->query('q'),
            $request->query('section'),
            $request->integer('admin_id') ?: null,
        ));
    }

    public function history(Request $request): JsonResponse
    {
        return response()->json($this->service->conversationHistoryGrouped(
            $request->user(),
            $request->query('q'),
            $request->integer('admin_id') ?: null,
        ));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->service->unreadSupportChatsForAdmin($request->user()),
        ]);
    }

    public function open(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $payload = $this->service->openTicketForAdmin($ticket, $request->user());
        $lastId = $this->latestMessageIdFromPayload($payload['messages'] ?? []);
        $this->service->markRead($ticket, $request->user(), $lastId);

        return response()->json([
            ...$payload,
            'context' => null,
        ]);
    }

    public function messages(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        if ($request->has('after_id')) {
            return response()->json([
                'items' => $this->service->messagesSince(
                    $ticket,
                    $request->user(),
                    $request->integer('after_id'),
                ),
            ]);
        }

        $result = $this->service->messages(
            $ticket,
            $request->user(),
            $request->integer('before_id') ?: null,
        );

        return response()->json($result);
    }

    public function send(SendCustomerSupportMessageRequest $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        return response()->json([
            'message' => $this->service->sendMessage(
                $ticket,
                $request->user(),
                $request->validated(),
                $request->file('attachments'),
            ),
        ]);
    }

    public function typing(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);
        abort_unless($this->service->canComposeOnTicket($ticket, $request->user()), 403);

        $data = $request->validate(['typing' => ['required', 'boolean']]);
        $this->service->broadcastTyping($ticket, $request->user(), (bool) $data['typing']);

        return response()->json(['ok' => true]);
    }

    public function typingState(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        return response()->json([
            'typing' => $this->service->typingStateForTicket($ticket, $request->user()),
        ]);
    }

    public function read(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate([
            'last_message_id' => ['required', 'integer', 'min:1'],
        ]);

        $this->service->markRead($ticket, $request->user(), (int) $data['last_message_id']);

        return response()->json(['ok' => true]);
    }

    public function reconcileNotifications(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user?->role?->slug, ['admin', 'super_admin'], true), 403);

        return response()->json([
            'cleared' => $this->service->reconcileStaffSupportNotifications($user),
        ]);
    }

    public function end(Request $request, SupportTicket $ticket): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);
        $closed = $this->service->endConversation($request->user(), $ticket, $data['note'] ?? null);

        return response()->json(['ticket' => $this->service->ticketListPayload($closed, $request->user())]);
    }

    public function reassign(ReassignCustomerSupportChatRequest $request, SupportTicket $ticket): JsonResponse
    {
        $updated = $this->service->reassignChat($request->user(), $ticket, (int) $request->validated('admin_id'));

        return response()->json(['ticket' => $this->service->ticketListPayload($updated, $request->user())]);
    }

    public function search(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return response()->json([
            'results' => $this->service->searchConversations($request->user(), $request->query('q')),
        ]);
    }

    public function userContext(Request $request, User $user): JsonResponse
    {
        abort_unless(in_array($request->user()?->role?->slug, ['admin', 'super_admin'], true), 403);

        return response()->json($this->safeUserContext($user, $request->user()));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeUserContext(User $customer, User $viewer): ?array
    {
        try {
            return $this->service->userContext($customer, $viewer);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function gifSearch(Request $request): JsonResponse
    {
        return response()->json($this->gifSearch->search($request->query('q')));
    }

    public function react(Request $request, SupportTicket $ticket, SupportTicketMessage $message): JsonResponse
    {
        abort_unless($this->service->canAccessTicket($ticket, $request->user()), 403);

        $data = $request->validate(['emoji' => ['required', 'string', 'max:8']]);

        return response()->json([
            'message' => $this->service->reactToMessage($ticket, $message, $request->user(), $data['emoji']),
        ]);
    }

    private function processUnassignedQueue(): void
    {
        SupportTicket::query()
            ->whereNull('opened_by_admin_id')
            ->where('chat_status', 'queued')
            ->whereNull('assigned_admin_id')
            ->orderBy('queued_at')
            ->limit(5)
            ->get()
            ->each(fn (SupportTicket $t) => $this->service->assignNextAvailableAdmin($t));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function onlineAdminsPayload(): array
    {
        return User::query()
            ->whereIn('id', $this->service->onlineAdminIds())
            ->with('role:id,slug')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role_id'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role?->slug,
            ])
            ->all();
    }

    /**
     * Staff admins available for super-admin reassignment (plus self).
     *
     * @return list<array<string, mixed>>
     */
    private function reassignTargetsPayload(User $actor): array
    {
        $onlineIds = $this->service->onlineAdminIds();
        $staff = $this->staffAdminsPayload($onlineIds);
        if ($actor->role?->slug !== 'super_admin') {
            return $staff;
        }

        $self = [
            'id' => $actor->id,
            'name' => $actor->name.' (assign to me)',
            'email' => $actor->email,
            'online' => $onlineIds->contains($actor->id),
        ];

        if (collect($staff)->contains(fn (array $row) => (int) $row['id'] === (int) $actor->id)) {
            return $staff;
        }

        return array_merge([$self], $staff);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function staffAdminsPayload(?\Illuminate\Support\Collection $onlineIds = null): array
    {
        $onlineIds ??= $this->service->onlineAdminIds();

        return User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'admin'))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'online' => $onlineIds->contains($u->id),
            ])
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     */
    private function latestMessageIdFromPayload(array $messages): ?int
    {
        if ($messages === []) {
            return null;
        }

        $last = $messages[array_key_last($messages)];

        return isset($last['id']) ? (int) $last['id'] : null;
    }
}
