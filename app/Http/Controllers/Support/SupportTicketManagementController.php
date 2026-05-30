<?php

namespace App\Http\Controllers\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreManagedSupportTicketRequest;
use App\Http\Requests\Support\UpdateManagedSupportTicketRequest;
use App\Http\Requests\Support\UpdateManagedSupportTicketStatusRequest;
use App\Models\SupportTicket;
use App\Models\SupportTicketIssueGroup;
use App\Models\User;
use App\Services\Support\SupportTicketManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketManagementController extends Controller
{
    public function __construct(private readonly SupportTicketManagementService $tickets) {}

    public function create(Request $request): Response
    {
        $routePrefix = str_starts_with((string) $request->route()?->getName(), 'admin.') ? 'admin' : 'operations';

        $prefillCustomer = null;
        $customerId = $request->integer('customer_id');
        if ($customerId) {
            $customer = User::query()->with('role:id,name,slug')->find($customerId);
            if ($customer) {
                $prefillCustomer = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                    'username' => $customer->username,
                    'role' => $customer->role?->name,
                    'role_slug' => $customer->role?->slug,
                    'status' => $customer->suspended_at ? 'Suspended' : ($customer->email_verified_at ? 'Active' : 'Unverified'),
                    'joined_at' => $customer->created_at?->toIso8601String(),
                ];
            }
        }

        return Inertia::render('Support/Tickets/Create', [
            'issueGroups' => $this->tickets->issueGroups(),
            'routePrefix' => $routePrefix,
            'prefillCustomerId' => $customerId ?: null,
            'prefillCustomer' => $prefillCustomer,
        ]);
    }

    public function store(StoreManagedSupportTicketRequest $request): RedirectResponse
    {
        $ticket = $this->tickets->createTicket(
            $request->user(),
            $request->validated(),
            $request->file('attachments', []) ?? [],
        );

        $route = str_starts_with((string) $request->route()?->getName(), 'admin.')
            ? 'admin.support-tickets.show'
            : 'operations.support-tickets.show';

        return redirect()->route($route, $ticket->uuid)->with('success', __('Ticket :ref created.', ['ref' => $ticket->ticket_reference]));
    }

    public function show(Request $request, SupportTicket $ticket): Response
    {
        $routePrefix = str_starts_with((string) $request->route()?->getName(), 'admin.') ? 'admin' : 'operations';

        return Inertia::render('Support/Tickets/Show', [
            'ticket' => $this->tickets->ticketDetail($ticket, $request->user()),
            'issueGroups' => $this->tickets->issueGroups(),
            'assignableAdmins' => collect($this->tickets->assignableAdmins())->map->only(['id', 'name', 'email']),
            'statuses' => SupportTicketManagementService::STATUSES,
            'routePrefix' => $routePrefix,
            'isSuperAdmin' => $request->user()?->role?->slug === 'super_admin',
            'sla_clock' => app(\App\Services\Platform\PlatformSlaService::class)->countdownPayload(
                app(\App\Services\Platform\PlatformSlaService::class)->activeClockForSubject('support_ticket_response', $ticket),
            ),
        ]);
    }

    public function index(Request $request): Response
    {
        $filters = $request->only(['status', 'priority', 'issue_group', 'assignee_id', 'sla_breached', 'from', 'to', 'search']);
        $isSuperAdmin = $request->user()?->role?->slug === 'super_admin';

        return Inertia::render('Support/Tickets/Index', [
            'ticketRows' => $this->tickets->listRows($request->user(), $filters),
            'filters' => $filters,
            'issueGroups' => $this->tickets->issueGroups(),
            'assignableAdmins' => collect($this->tickets->assignableAdmins())->map->only(['id', 'name', 'email']),
            'analytics' => $isSuperAdmin ? $this->tickets->analytics() : null,
            'statuses' => SupportTicketManagementService::STATUSES,
            'routePrefix' => str_starts_with((string) $request->route()?->getName(), 'admin.') ? 'admin' : 'operations',
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function searchCustomers(Request $request): JsonResponse
    {
        return response()->json([
            'items' => $this->tickets->searchCustomers((string) $request->query('q', '')),
        ]);
    }

    public function updateStatus(UpdateManagedSupportTicketStatusRequest $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        $this->tickets->updateStatus(
            $request->user(),
            $ticket,
            $request->validated('status'),
            $request->validated('summary'),
        );

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('Ticket status updated.'),
                'ticket' => $this->tickets->ticketDetail($ticket->fresh(), $request->user()),
            ]);
        }

        return back()->with('success', __('Ticket status updated.'));
    }

    public function reassign(Request $request, SupportTicket $ticket): RedirectResponse|JsonResponse
    {
        $data = $request->validate(['assignee_id' => ['required', 'integer', 'exists:users,id']]);
        $this->tickets->reassign($request->user(), $ticket, (int) $data['assignee_id']);

        if ($request->wantsJson()) {
            return response()->json([
                'message' => __('Ticket reassigned.'),
                'ticket' => $this->tickets->ticketDetail($ticket->fresh(), $request->user()),
            ]);
        }

        return back()->with('success', __('Ticket reassigned.'));
    }

    public function addComment(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:20000'],
            'customer_facing' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:5120'],
        ]);

        $this->tickets->addComment(
            $request->user(),
            $ticket,
            $data['body'],
            (bool) ($data['customer_facing'] ?? false),
            $request->file('attachments', []) ?? [],
        );

        return back()->with('success', __('Comment added.'));
    }

    public function update(UpdateManagedSupportTicketRequest $request, SupportTicket $ticket): RedirectResponse
    {
        $this->tickets->updateTicket($request->user(), $ticket, $request->validated());

        return back()->with('success', __('Ticket updated.'));
    }

    public function destroy(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $routePrefix = str_starts_with((string) $request->route()?->getName(), 'admin.') ? 'admin' : 'operations';
        $this->tickets->deleteTicket($request->user(), $ticket);

        return redirect()->route("{$routePrefix}.support-tickets.index")->with('success', __('Ticket deleted.'));
    }

    public function updateActionItems(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'action_items' => ['required', 'array', 'max:20'],
            'action_items.*.id' => ['nullable', 'string', 'max:80'],
            'action_items.*.label' => ['required', 'string', 'max:500'],
            'action_items.*.completed' => ['nullable', 'boolean'],
        ]);

        $this->tickets->updateActionItems($request->user(), $ticket, $data['action_items']);

        return back()->with('success', __('Action checklist updated.'));
    }

    public function overrideSla(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'expected_resolution_at' => ['required', 'date', 'after:today'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $this->tickets->overrideSla($request->user(), $ticket, $data['expected_resolution_at'], $data['reason']);

        return back()->with('success', __('Expected resolution date updated.'));
    }

    public function merge(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'duplicate_ticket_id' => ['required', 'integer', 'exists:support_tickets,id'],
        ]);

        $duplicate = SupportTicket::query()->findOrFail((int) $data['duplicate_ticket_id']);
        $this->tickets->mergeTickets($request->user(), $ticket, $duplicate);

        return back()->with('success', __('Tickets merged.'));
    }

    public function issueGroupSettings(Request $request): Response
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Admin/SupportTickets/IssueGroups', [
            'groups' => $this->tickets->allIssueGroupsForSettings(),
        ]);
    }

    public function storeIssueGroup(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->tickets->upsertIssueGroup($data);

        return back()->with('success', __('Issue group saved.'));
    }

    public function updateIssueGroup(Request $request, SupportTicketIssueGroup $issueGroup): RedirectResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $data = $request->validate([
            'key' => ['nullable', 'string', 'max:80'],
            'label' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->tickets->upsertIssueGroup($data, $issueGroup);

        return back()->with('success', __('Issue group updated.'));
    }
}
