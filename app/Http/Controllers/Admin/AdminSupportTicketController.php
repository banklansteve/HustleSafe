<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApproveStaffBulkMessageRequest;
use App\Http\Requests\Operations\UpdateSupportTicketStatusRequest;
use App\Models\StaffBulkMessageRequest;
use App\Models\SupportTicket;
use App\Services\Operations\StaffSupportMessagingService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminSupportTicketController extends Controller
{
    public function index(StaffSupportMessagingService $service): Response
    {
        $service->ensureAssignments();

        return Inertia::render('Admin/SupportTickets/Index', [
            'tickets' => $service->ticketsForAdmin(),
            'bulkRequests' => $service->pendingBulkRequests(),
            'chatAssignments' => $service->allChats(),
        ]);
    }

    public function updateTicketStatus(UpdateSupportTicketStatusRequest $request, SupportTicket $ticket, StaffSupportMessagingService $service): RedirectResponse
    {
        $service->updateTicketStatus($request->user(), $ticket, $request->validated('status'), $request->validated('reason'));

        return back()->with('success', 'Ticket status updated.');
    }

    public function approveBulkMessage(ApproveStaffBulkMessageRequest $request, StaffBulkMessageRequest $bulkMessage, StaffSupportMessagingService $service): RedirectResponse
    {
        $service->approveBulkMessage($request->user(), $bulkMessage, $request->validated('approval_note'));

        return back()->with('success', 'Bulk message authorised and dispatched.');
    }
}
