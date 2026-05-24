<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffBulkMessageRequest;
use App\Http\Requests\Operations\StoreSupportTicketRequest;
use App\Http\Requests\Operations\UpdateSupportTicketStatusRequest;
use App\Models\SupportChatAssignment;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Operations\StaffSupportHubService;
use App\Services\Operations\StaffSupportMessagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsSupportHubController extends Controller
{
    public function index(StaffSupportHubService $hub, StaffSupportMessagingService $messaging): Response
    {
        $messaging->ensureAssignments();

        return Inertia::render('Operations/Support/Index', [
            'ticket_queues' => $hub->ticketQueues(),
            'dispute_queues' => $hub->disputeQueues(),
            'support_tables_ready' => $messaging->supportTablesReady(),
            'bulk_requests' => $messaging->pendingBulkRequestsForStaff(request()->user()),
        ]);
    }

    public function search(Request $request, StaffSupportHubService $hub): JsonResponse
    {
        return response()->json($hub->globalSearch($request));
    }

    public function tickets(Request $request, StaffSupportHubService $hub): JsonResponse
    {
        $queue = (string) $request->input('queue', 'my_tickets');

        return response()->json($hub->ticketsFor($request->user(), $queue));
    }

    public function chats(Request $request, StaffSupportHubService $hub): JsonResponse
    {
        return response()->json($hub->chatsWaiting($request->user()));
    }

    public function disputes(Request $request, StaffSupportHubService $hub): JsonResponse
    {
        return response()->json($hub->disputes((string) $request->input('queue', 'all_open')));
    }

    public function storeBulkMessage(StoreStaffBulkMessageRequest $request, StaffSupportMessagingService $service): RedirectResponse
    {
        $service->createBulkMessageRequest($request->user(), $request->validated());

        return back()->with('success', 'Bulk message submitted for Super Admin authorisation.');
    }

    public function storeTicket(StoreSupportTicketRequest $request, StaffSupportMessagingService $service): RedirectResponse
    {
        $service->createTicket($request->user(), $request->validated());

        return back()->with('success', 'Support ticket opened and customer confirmation sent.');
    }

    public function updateTicketStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket, StaffSupportMessagingService $service): JsonResponse
    {
        $service->updateTicketStatus($request->user(), $ticket, $request->validated('status'), $request->validated('reason'));

        return response()->json(['message' => 'Ticket updated.']);
    }

    public function unassignedChats(StaffSupportMessagingService $messaging): JsonResponse
    {
        return response()->json(['items' => $messaging->unassignedChats()->values()->all()]);
    }

    public function claimChat(Request $request, SupportChatAssignment $assignment, StaffSupportMessagingService $messaging): JsonResponse
    {
        $messaging->claimChat($assignment, $request->user());

        return response()->json(['message' => 'Chat assigned to you.']);
    }

    public function chatDetail(SupportChatAssignment $assignment, StaffSupportMessagingService $messaging): JsonResponse
    {
        return response()->json($messaging->chatThread($assignment));
    }

    public function chatReply(Request $request, SupportChatAssignment $assignment, StaffSupportMessagingService $messaging): JsonResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:5000']]);
        $messaging->replyToChat($assignment, $request->user(), $data['body']);

        return response()->json(['message' => 'Reply sent.']);
    }

    public function userContext(User $user, StaffSupportMessagingService $messaging): JsonResponse
    {
        return response()->json($messaging->userContext($user));
    }

    public function panelEmail(Request $request, User $user, StaffSupportMessagingService $messaging): JsonResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'min:8', 'max:5000'],
            'channel' => ['nullable', 'in:both,email,in_app'],
            'context' => ['nullable', 'string', 'max:120'],
        ]);

        $messaging->sendPanelEmail($request->user(), $user, $data);

        return response()->json(['message' => 'Email sent.']);
    }

    public function ticketDetail(SupportTicket $ticket, StaffSupportMessagingService $messaging): JsonResponse
    {
        $ticket->load(['customer:id,name,email', 'messages.sender:id,name,email', 'assignedAdmin:id,name,email']);

        return response()->json(['ticket' => $messaging->ticketPayload($ticket)]);
    }
}
