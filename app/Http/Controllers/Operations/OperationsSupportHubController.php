<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffBulkMessageRequest;
use App\Http\Requests\Operations\StoreSupportTicketRequest;
use App\Http\Requests\Operations\UpdateSupportTicketStatusRequest;
use App\Models\SupportTicket;
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
}
