<?php

namespace App\Http\Controllers\Operations;

use App\Enums\StaffLeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffLeaveRequest;
use App\Models\StaffLeaveRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsHrSelfServiceController extends Controller
{
    public function index(): Response
    {
        return Inertia::location(route('operations.account.index'));
    }

    public function storeLeaveRequest(StoreStaffLeaveRequest $request): RedirectResponse
    {
        $staff = $request->user();
        $startDate = CarbonImmutable::parse($request->validated('start_date'));
        $endDate = CarbonImmutable::parse($request->validated('end_date'));
        $days = max(1, $startDate->diffInDays($endDate) + 1);

        StaffLeaveRequest::query()->create([
            'staff_user_id' => $staff->id,
            'leave_type' => $request->validated('leave_type'),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days_requested' => $days,
            'reason' => $request->validated('reason'),
            'status' => StaffLeaveRequestStatus::Pending->value,
        ]);

        return redirect()->route('operations.account.index')->with('success', __('Leave request submitted for review.'));
    }
}
