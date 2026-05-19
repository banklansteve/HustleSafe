<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffBulkMessageRequest;
use App\Http\Requests\Operations\StoreSupportTicketRequest;
use App\Http\Requests\Operations\UpdateSupportTicketStatusRequest;
use App\Models\QuestConversationThread;
use App\Models\SupportTicket;
use App\Services\Operations\StaffSupportMessagingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsCommunicationsController extends Controller
{
    public function index(StaffSupportMessagingService $service): Response
    {
        $service->ensureAssignments();

        $threads = QuestConversationThread::query()
            ->with(['quest:id,title,reference_code', 'client:id,name,email', 'freelancer:id,name,email'])
            ->latest('last_message_at')
            ->paginate(20)
            ->through(fn (QuestConversationThread $thread) => [
                'id' => $thread->id,
                'quest' => $thread->quest?->title,
                'reference_code' => $thread->quest?->reference_code,
                'client' => $thread->client?->name,
                'freelancer' => $thread->freelancer?->name,
                'messages_count' => $thread->messages_count,
                'last_message_at' => $thread->last_message_at?->toIso8601String(),
                'blocked' => $thread->isBlockedByAdmin(),
            ]);

        return Inertia::render('Operations/Communications/Index', [
            'threads' => $threads,
            'assignedChats' => $service->assignedChats(request()->user()),
            'tickets' => $service->ticketsForStaff(request()->user()),
            'bulkRequests' => $service->pendingBulkRequestsForStaff(request()->user()),
            'support_tables_ready' => $service->supportTablesReady(),
        ]);
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

    public function updateTicketStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket, StaffSupportMessagingService $service): RedirectResponse
    {
        $service->updateTicketStatus($request->user(), $ticket, $request->validated('status'), $request->validated('reason'));

        return back()->with('success', 'Support ticket status updated.');
    }
}
