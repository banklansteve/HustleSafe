<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffLeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustStaffLeaveBalanceRequest;
use App\Http\Requests\Admin\AssignStaffRoleGroupRequest;
use App\Http\Requests\Admin\ReviewStaffLeaveRequest;
use App\Http\Requests\Admin\StoreStaffComplianceCaseRequest;
use App\Http\Requests\Admin\StoreStaffPayrollAllowanceRequest;
use App\Http\Requests\Admin\StoreStaffPayrollDeductionRequest;
use App\Http\Requests\Admin\StoreStaffPayrollAdjustmentRequest;
use App\Http\Requests\Admin\StoreSuspiciousActivityFlagRequest;
use App\Http\Requests\Admin\UpdateStaffPayrollAllowanceRequest;
use App\Http\Requests\Admin\UpdateStaffPayrollDeductionRequest;
use App\Http\Requests\Admin\UpdateStaffComplianceCaseStatusRequest;
use App\Http\Requests\Admin\UpdateStaffPayrollProfileRequest;
use App\Models\StaffHrComplianceCase;
use App\Models\StaffHrAuditTrail;
use App\Models\StaffHrAlert;
use App\Models\StaffHrSuspiciousActivityFlag;
use App\Models\StaffLeaveBalance;
use App\Models\StaffLeaveRequest;
use App\Models\StaffPayrollAdjustment;
use App\Models\StaffPayrollProfile;
use App\Models\StaffPayslip;
use App\Models\StaffRoleAssignment;
use App\Models\User;
use App\Services\Hr\HrAuditTrailService;
use App\Services\Hr\StaffHrImpactNotificationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use App\Support\AdminCsv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminHrManagementController extends Controller
{
    public function __construct(
        private readonly HrAuditTrailService $auditTrail,
        private readonly StaffHrImpactNotificationService $staffNotifications,
    ) {}

    public function index(Request $request): SymfonyResponse
    {
        return Inertia::location(route('admin.hr.roles.index'));
    }

    public function rolesIndex(): Response
    {
        $staff = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'admin'))
            ->select(['id', 'name', 'email'])
            ->orderBy('name')
            ->get();

        $activeAssignments = StaffRoleAssignment::query()
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString());
            })
            ->with('staff:id,name,email')
            ->latest('id')
            ->get();

        $roleCoverage = $this->roleCoverageSummary();
        $benchmarkBreaches = $this->benchmarkBreachesSummary();

        return Inertia::render('Admin/Hr/Roles', [
            'staff' => $staff,
            'activeAssignments' => $activeAssignments,
            'roleCoverage' => $roleCoverage,
            'benchmarkBreaches' => $benchmarkBreaches,
        ]);
    }

    public function leaveIndex(): Response
    {
        $staff = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'admin'))->select(['id', 'name', 'email'])->orderBy('name')->get();
        $leaveRequests = StaffLeaveRequest::query()->with('staff:id,name,email')->latest('id')->limit(80)->get();
        $leaveCalendar = StaffLeaveRequest::query()->where('status', StaffLeaveRequestStatus::Approved->value)->whereDate('end_date', '>=', now()->toDateString())->with('staff:id,name,email')->orderBy('start_date')->limit(120)->get();
        $staffLeaveBalances = StaffLeaveBalance::query()->with('staff:id,name,email')->orderByDesc('year')->orderByDesc('updated_at')->limit(200)->get();

        return Inertia::render('Admin/Hr/Leave', [
            'staff' => $staff,
            'leaveRequests' => $leaveRequests,
            'leaveCalendar' => $leaveCalendar,
            'staffLeaveBalances' => $staffLeaveBalances,
        ]);
    }

    public function paymentsIndex(Request $request): Response
    {
        $selectedYear = max(2000, min(2100, (int) $request->integer('year', now()->year)));
        $selectedMonth = max(1, min(12, (int) $request->integer('month', now()->month)));

        $staff = User::query()->whereHas('role', fn ($query) => $query->where('slug', 'admin'))->select(['id', 'name', 'email'])->orderBy('name')->get();
        $payrollProfiles = StaffPayrollProfile::query()->latest('updated_at')->limit(80)->get();
        $payrollAdjustments = StaffPayrollAdjustment::query()->latest('id')->limit(120)->get();
        $payrollAllowances = StaffPayrollAdjustment::query()->where('type', 'bonus')->where('is_recurring', true)->latest('id')->limit(300)->get();
        $payrollDeductions = StaffPayrollAdjustment::query()->where('type', 'deduction')->where('is_recurring', true)->latest('id')->limit(300)->get();
        $staffPayslips = StaffPayslip::query()->with('staff:id,name,email')->orderByDesc('year')->orderByDesc('month')->limit(200)->get();

        return Inertia::render('Admin/Hr/Payments', [
            'staff' => $staff,
            'payrollProfiles' => $payrollProfiles,
            'payrollAdjustments' => $payrollAdjustments,
            'payrollAllowances' => $payrollAllowances,
            'payrollDeductions' => $payrollDeductions,
            'staffPayslips' => $staffPayslips,
            'selectedYear' => $selectedYear,
            'selectedMonth' => $selectedMonth,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function roleCoverageSummary(): array
    {
        $today = now()->toDateString();
        $groups = [
            'group_a_chat_communications',
            'group_b_moderation_operations',
            'group_c_people_trust_management',
            'group_d_financial_disputes_casework',
        ];

        $counts = StaffRoleAssignment::query()
            ->select('role_group', DB::raw('COUNT(*) as total'))
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today);
            })
            ->groupBy('role_group')
            ->pluck('total', 'role_group');

        $items = [];
        $zeroCoverage = [];
        foreach ($groups as $group) {
            $count = (int) ($counts[$group] ?? 0);
            $items[] = ['role_group' => $group, 'label' => $this->roleGroupLabel($group), 'headcount' => $count];
            if ($count === 0) {
                $zeroCoverage[] = $this->roleGroupLabel($group);
            }
        }

        return [
            'items' => $items,
            'zero_coverage_groups' => $zeroCoverage,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function benchmarkBreachesSummary(): array
    {
        $now = now();
        $weekStartA = $now->copy()->startOfWeek()->subWeek();
        $weekEndA = $weekStartA->copy()->endOfWeek();
        $weekStartB = $weekStartA->copy()->subWeek();
        $weekEndB = $weekStartB->copy()->endOfWeek();

        $benchmarks = DB::table('staff_activity_benchmarks')->get()->keyBy('role_group');
        if ($benchmarks->isEmpty()) {
            return [];
        }

        $assignments = StaffRoleAssignment::query()
            ->where('status', 'active')
            ->whereDate('starts_on', '<=', $now->toDateString())
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $now->toDateString());
            })
            ->get(['staff_user_id', 'role_group']);

        $breaches = [];
        foreach ($assignments as $assignment) {
            $benchmark = $benchmarks->get($assignment->role_group);
            if (! $benchmark) {
                continue;
            }

            $minimum = (int) $benchmark->minimum_weekly_actions;
            if ($minimum <= 0) {
                continue;
            }

            $countA = (int) DB::table('staff_action_logs')
                ->where('staff_user_id', $assignment->staff_user_id)
                ->whereBetween('acted_at', [$weekStartA, $weekEndA])
                ->count();
            $countB = (int) DB::table('staff_action_logs')
                ->where('staff_user_id', $assignment->staff_user_id)
                ->whereBetween('acted_at', [$weekStartB, $weekEndB])
                ->count();

            if ($countA < $minimum && $countB < $minimum) {
                $breaches[] = [
                    'staff_user_id' => (int) $assignment->staff_user_id,
                    'role_group' => $assignment->role_group,
                    'minimum' => $minimum,
                    'week_a_count' => $countA,
                    'week_b_count' => $countB,
                ];
            }
        }

        return $breaches;
    }

    public function assignRoleGroup(AssignStaffRoleGroupRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $staffUserId = (int) $data['staff_user_id'];
        $selectedGroups = array_values(array_unique($data['role_groups']));

        $existing = StaffRoleAssignment::query()
            ->where('staff_user_id', $staffUserId)
            ->where('status', 'active')
            ->get();

        $existingByGroup = $existing->keyBy('role_group');
        $created = [];
        $updated = [];
        $revoked = [];

        foreach ($existing as $row) {
            if (! in_array($row->role_group, $selectedGroups, true)) {
                $row->update([
                    'status' => 'revoked',
                    'revoked_at' => now(),
                    'revoked_by_user_id' => $actor->id,
                    'revoked_reason' => 'Removed from staff role-group assignment.',
                ]);
                $revoked[] = $row->role_group;
            }
        }

        foreach ($selectedGroups as $roleGroup) {
            if ($existingByGroup->has($roleGroup)) {
                $assignment = $existingByGroup->get($roleGroup);
                $assignment->update([
                    'starts_on' => $data['starts_on'],
                    'ends_on' => $data['ends_on'] ?? null,
                    'reason' => $data['reason'],
                ]);
                $updated[] = $assignment->fresh()->toArray();

                continue;
            }

            $assignment = StaffRoleAssignment::query()->create([
                'staff_user_id' => $staffUserId,
                'role_group' => $roleGroup,
                'starts_on' => $data['starts_on'],
                'ends_on' => $data['ends_on'] ?? null,
                'status' => 'active',
                'reason' => $data['reason'],
                'assigned_by_user_id' => $actor->id,
            ]);
            $created[] = $assignment->toArray();
        }

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.role_assignment.changed',
            targetStaffUserId: $staffUserId,
            after: [
                'staff_user_id' => $staffUserId,
                'role_groups' => $selectedGroups,
                'created' => $created,
                'updated' => $updated,
                'revoked' => $revoked,
            ],
            metadata: ['reason' => $data['reason']],
            request: $request,
        );

        $staff = User::query()->find($staffUserId);
        if ($staff !== null) {
            $this->staffNotifications->notifyRoleAssignmentChanged(
                $staff,
                $selectedGroups,
                $created,
                $revoked,
                $data['starts_on'],
                $data['ends_on'] ?? null,
            );
        }

        return redirect()->back()->with('success', __('Role group assignments saved.'));
    }

    public function reviewLeaveRequest(ReviewStaffLeaveRequest $request, StaffLeaveRequest $leaveRequest): RedirectResponse
    {
        $actor = $request->user();
        $before = $leaveRequest->toArray();
        $status = $request->validated('status');

        $leaveRequest->update([
            'status' => $status,
            'review_note' => $request->validated('review_note'),
            'reviewed_by_user_id' => $actor->id,
            'reviewed_at' => now(),
        ]);

        if ($status === StaffLeaveRequestStatus::Approved->value) {
            $year = (int) $leaveRequest->start_date->format('Y');
            $balance = StaffLeaveBalance::query()->firstOrCreate(
                ['staff_user_id' => $leaveRequest->staff_user_id, 'year' => $year],
                ['annual_days' => 0, 'sick_days' => 0, 'emergency_days' => 0, 'unpaid_days' => 0]
            );

            $leaveType = (string) $leaveRequest->leave_type;
            $remaining = $balance->remainingDays($leaveType);

            if ($leaveRequest->days_requested > $remaining) {
                return redirect()->back()->withErrors([
                    'review_note' => __('Not enough leave balance. Available: :days day(s).', ['days' => $remaining]),
                ]);
            }

            $balance->increment(StaffLeaveBalance::usedColumnFor($leaveType), $leaveRequest->days_requested);
        }

        $leaveRequest->refresh();
        $leaveRequest->staff?->loadMissing('role:id,slug');
        if ($leaveRequest->staff !== null) {
            $this->staffNotifications->notifyLeaveRequestReviewed($leaveRequest);
        }

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.leave_request.reviewed',
            targetStaffUserId: (int) $leaveRequest->staff_user_id,
            before: $before,
            after: $leaveRequest->fresh()?->toArray(),
            metadata: ['note' => $request->validated('review_note')],
            request: $request,
        );

        return redirect()->back()->with('success', __('Leave request updated.'));
    }

    public function adjustLeaveBalance(AdjustStaffLeaveBalanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $balance = StaffLeaveBalance::query()->firstOrCreate(
            ['staff_user_id' => $data['staff_user_id'], 'year' => $data['year']],
            ['annual_days' => 0, 'sick_days' => 0, 'emergency_days' => 0, 'unpaid_days' => 0]
        );
        $before = $balance->toArray();

        $assignedColumn = StaffLeaveBalance::assignedColumnFor($data['leave_type']);
        $usedColumn = StaffLeaveBalance::usedColumnFor($data['leave_type']);
        $assigned = (int) $balance->{$assignedColumn};
        $used = (int) $balance->{$usedColumn};

        if ($data['mode'] === 'allocate') {
            if ($assigned > 0) {
                return redirect()->back()->withErrors([
                    'leave_type' => __('Leave is already allocated for this type. Use add or remove to adjust the balance.'),
                ]);
            }

            $balance->update([$assignedColumn => $data['days']]);
            $message = __('Leave allocation saved.');
            $actionType = 'hr.leave_balance.allocated';
        } else {
            if ($assigned <= 0) {
                return redirect()->back()->withErrors([
                    'leave_type' => __('Allocate leave for this type before adding or removing days.'),
                ]);
            }

            $direction = $data['adjustment_direction'];
            $newAssigned = $direction === 'add'
                ? $assigned + $data['days']
                : $assigned - $data['days'];

            if ($newAssigned < $used) {
                $availableToRemove = max(0, $assigned - $used);

                return redirect()->back()->withErrors([
                    'days' => __('Cannot remove :requested day(s). Only :available day(s) can be removed without going negative.', [
                        'requested' => $data['days'],
                        'available' => $availableToRemove,
                    ]),
                ]);
            }

            $balance->update([$assignedColumn => max(0, $newAssigned)]);
            $message = $direction === 'add'
                ? __('Added :days day(s) to leave balance.', ['days' => $data['days']])
                : __('Removed :days day(s) from leave balance.', ['days' => $data['days']]);
            $actionType = 'hr.leave_balance.adjusted';
        }

        $this->auditTrail->log(
            actor: $actor,
            actionType: $actionType,
            targetStaffUserId: (int) $data['staff_user_id'],
            before: $before,
            after: $balance->fresh()?->toArray(),
            metadata: [
                'reason' => $data['reason'],
                'mode' => $data['mode'],
                'adjustment_direction' => $data['adjustment_direction'] ?? null,
                'days' => $data['days'],
                'leave_type' => $data['leave_type'],
            ],
            request: $request,
        );

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifyLeaveBalanceChanged(
                $staff,
                $data['leave_type'],
                $data['mode'] === 'allocate' ? 'allocate' : ($data['adjustment_direction'] ?? 'remove'),
                $data['days'],
                $data['year'],
                $data['reason'],
            );
        }

        return redirect()->back()->with('success', $message);
    }

    public function updatePayrollProfile(UpdateStaffPayrollProfileRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $effectiveFrom = CarbonImmutable::createFromDate((int) $data['wef_year'], (int) $data['wef_month'], 1)->toDateString();

        $profile = StaffPayrollProfile::query()->firstOrCreate(
            ['staff_user_id' => $data['staff_user_id']],
            ['base_salary' => 0, 'currency' => 'NGN', 'payment_frequency' => 'monthly']
        );
        $before = $profile->toArray();

        $profile->update([
            'base_salary' => $data['base_salary'],
            'currency' => 'NGN',
            'payment_frequency' => 'monthly',
            'effective_from' => $effectiveFrom,
            'bank_details_encrypted' => json_encode([
                'bank_name' => $data['bank_name'] ?? null,
                'bank_account_name' => $data['bank_account_name'] ?? null,
                'bank_account_number' => $data['bank_account_number'] ?? null,
            ], JSON_THROW_ON_ERROR),
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.payroll_profile.updated',
            targetStaffUserId: (int) $data['staff_user_id'],
            before: $before,
            after: $profile->fresh()?->toArray(),
            request: $request,
        );

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollProfileUpdated(
                $staff,
                (float) $data['base_salary'],
                $effectiveFrom,
            );
        }

        return redirect()->back()->with('success', __('Payroll profile updated.'));
    }

    public function storePayrollAdjustment(StoreStaffPayrollAdjustmentRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $adjustment = StaffPayrollAdjustment::query()->create([
            'staff_user_id' => $data['staff_user_id'],
            'type' => $data['type'],
            'amount' => $data['amount'],
            'reason' => $data['reason'],
            'effective_date' => $data['effective_date'],
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
            'reference' => $data['reference'] ?? null,
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.payroll_adjustment.created',
            targetStaffUserId: (int) $data['staff_user_id'],
            after: $adjustment->toArray(),
            request: $request,
        );

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollAdjustmentCreated(
                $staff,
                $data['type'],
                (float) $data['amount'],
                $data['reason'],
                $data['effective_date'],
            );
        }

        return redirect()->back()->with('success', __('Payroll adjustment logged.'));
    }

    public function storePayrollAllowance(StoreStaffPayrollAllowanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $effectiveFrom = CarbonImmutable::createFromDate((int) $data['wef_year'], (int) $data['wef_month'], 1)->toDateString();

        $allowance = StaffPayrollAdjustment::query()->create([
            'staff_user_id' => $data['staff_user_id'],
            'type' => 'bonus',
            'amount' => $data['amount'],
            'reason' => 'Recurring allowance: '.$data['title'],
            'effective_date' => $effectiveFrom,
            'is_recurring' => true,
            'reference' => $data['title'],
            'created_by_user_id' => $actor->id,
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.payroll_allowance.created',
            targetStaffUserId: (int) $data['staff_user_id'],
            after: $allowance->toArray(),
            request: $request,
        );

        $staff = User::query()->find($allowance->staff_user_id);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollAllowanceChanged(
                $staff,
                $data['title'],
                (float) $data['amount'],
                'created',
                $effectiveFrom,
            );
        }

        return redirect()->back()->with('success', __('Allowance added.'));
    }

    public function updatePayrollAllowance(UpdateStaffPayrollAllowanceRequest $request, StaffPayrollAdjustment $allowance): RedirectResponse
    {
        abort_unless($allowance->type === 'bonus' && $allowance->is_recurring, 404);
        $actor = $request->user();
        $before = $allowance->toArray();
        $data = $request->validated();
        $effectiveFrom = CarbonImmutable::createFromDate((int) $data['wef_year'], (int) $data['wef_month'], 1)->toDateString();

        $allowance->update([
            'amount' => $data['amount'],
            'reference' => $data['title'],
            'reason' => 'Recurring allowance: '.$data['title'],
            'effective_date' => $effectiveFrom,
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.payroll_allowance.updated',
            targetStaffUserId: (int) $allowance->staff_user_id,
            before: $before,
            after: $allowance->fresh()?->toArray(),
            request: $request,
        );

        $staff = User::query()->find($allowance->staff_user_id);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollAllowanceChanged(
                $staff,
                $data['title'],
                (float) $data['amount'],
                'updated',
                $effectiveFrom,
            );
        }

        return redirect()->back()->with('success', __('Allowance updated.'));
    }

    public function destroyPayrollAllowance(StaffPayrollAdjustment $allowance, Request $request): RedirectResponse
    {
        abort_unless($allowance->type === 'bonus' && $allowance->is_recurring, 404);
        $actor = $request->user();
        $before = $allowance->toArray();
        $targetStaffUserId = (int) $allowance->staff_user_id;
        $allowanceTitle = (string) ($allowance->reference ?? 'Allowance');
        $allowanceAmount = (float) $allowance->amount;
        $allowance->delete();

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.payroll_allowance.deleted',
            targetStaffUserId: $targetStaffUserId,
            before: $before,
            request: $request,
        );

        $staff = User::query()->find($targetStaffUserId);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollAllowanceChanged(
                $staff,
                $allowanceTitle,
                $allowanceAmount,
                'deleted',
                (string) ($before['effective_date'] ?? now()->toDateString()),
            );
        }

        return redirect()->back()->with('success', __('Allowance removed.'));
    }

    public function storePayrollDeduction(StoreStaffPayrollDeductionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $mode = $data['deduction_mode'];
        $supportsAdvancedColumns = $this->supportsAdvancedDeductionColumns();
        $resolvedAmount = $this->resolveDeductionAmount($data);
        $effectiveFrom = CarbonImmutable::createFromDate((int) $data['wef_year'], (int) $data['wef_month'], 1)->toDateString();

        $deduction = StaffPayrollAdjustment::query()->create([
            'staff_user_id' => $data['staff_user_id'],
            'type' => 'deduction',
            'deduction_mode' => $supportsAdvancedColumns ? $mode : null,
            'deduction_basis' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_basis'] ?? 'basic_salary') : null,
            'deduction_percentage' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_percentage'] ?? null) : null,
            'deduction_custom_base_amount' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_custom_base_amount'] ?? null) : null,
            'amount' => $supportsAdvancedColumns
                ? ($mode === 'flat' ? (float) ($data['amount'] ?? 0) : 0)
                : $resolvedAmount,
            'reason' => 'Recurring deduction: '.$data['title'],
            'effective_date' => $effectiveFrom,
            'is_recurring' => true,
            'reference' => $data['title'],
            'created_by_user_id' => $actor->id,
        ]);

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollDeductionChanged(
                $staff,
                $data['title'],
                'created',
                $effectiveFrom,
            );
        }

        return redirect()->back()->with('success', __('Deduction added.'));
    }

    public function updatePayrollDeduction(UpdateStaffPayrollDeductionRequest $request, StaffPayrollAdjustment $deduction): RedirectResponse
    {
        abort_unless($deduction->type === 'deduction' && $deduction->is_recurring, 404);
        $data = $request->validated();
        $mode = $data['deduction_mode'];
        $supportsAdvancedColumns = $this->supportsAdvancedDeductionColumns();
        $resolvedAmount = $this->resolveDeductionAmount($data);
        $effectiveFrom = CarbonImmutable::createFromDate((int) $data['wef_year'], (int) $data['wef_month'], 1)->toDateString();

        $deduction->update([
            'deduction_mode' => $supportsAdvancedColumns ? $mode : null,
            'deduction_basis' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_basis'] ?? 'basic_salary') : null,
            'deduction_percentage' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_percentage'] ?? null) : null,
            'deduction_custom_base_amount' => $supportsAdvancedColumns && $mode === 'percentage' ? ($data['deduction_custom_base_amount'] ?? null) : null,
            'amount' => $supportsAdvancedColumns
                ? ($mode === 'flat' ? (float) ($data['amount'] ?? 0) : 0)
                : $resolvedAmount,
            'reference' => $data['title'],
            'reason' => 'Recurring deduction: '.$data['title'],
            'effective_date' => $effectiveFrom,
        ]);

        $staff = User::query()->find($deduction->staff_user_id);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollDeductionChanged(
                $staff,
                $data['title'],
                'updated',
                $effectiveFrom,
            );
        }

        return redirect()->back()->with('success', __('Deduction updated.'));
    }

    public function destroyPayrollDeduction(StaffPayrollAdjustment $deduction): RedirectResponse
    {
        abort_unless($deduction->type === 'deduction' && $deduction->is_recurring, 404);
        $staffUserId = (int) $deduction->staff_user_id;
        $title = (string) ($deduction->reference ?? 'Deduction');
        $deduction->delete();

        $staff = User::query()->find($staffUserId);
        if ($staff !== null) {
            $this->staffNotifications->notifyPayrollDeductionChanged($staff, $title, 'deleted');
        }

        return redirect()->back()->with('success', __('Deduction removed.'));
    }

    public function storeComplianceCase(StoreStaffComplianceCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $case = StaffHrComplianceCase::query()->create([
            'staff_user_id' => $data['staff_user_id'],
            'severity' => $data['severity'],
            'status' => 'open',
            'incident_note' => $data['incident_note'],
            'evidence' => $data['evidence'] ?? null,
            'opened_by_user_id' => $actor->id,
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.compliance_case.opened',
            targetStaffUserId: (int) $data['staff_user_id'],
            after: $case->toArray(),
            request: $request,
        );

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifyComplianceCaseOpened(
                $staff,
                $data['severity'],
                $data['incident_note'],
            );
        }

        return redirect()->route('admin.hr.index')->with('success', __('Compliance case opened.'));
    }

    public function updateComplianceCaseStatus(UpdateStaffComplianceCaseStatusRequest $request, StaffHrComplianceCase $case): RedirectResponse
    {
        $actor = $request->user();
        $before = $case->toArray();
        $status = $request->validated('status');

        $case->update([
            'status' => $status,
            'updated_by_user_id' => $actor->id,
            'resolved_at' => $status === 'resolved' ? now() : null,
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.compliance_case.status_updated',
            targetStaffUserId: (int) $case->staff_user_id,
            before: $before,
            after: $case->fresh()?->toArray(),
            metadata: ['note' => $request->validated('note')],
            request: $request,
        );

        $staff = User::query()->find($case->staff_user_id);
        if ($staff !== null) {
            $this->staffNotifications->notifyComplianceCaseStatusUpdated(
                $staff,
                $status,
                $request->validated('note'),
            );
        }

        return redirect()->route('admin.hr.index')->with('success', __('Compliance case updated.'));
    }

    public function storeSuspiciousFlag(StoreSuspiciousActivityFlagRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        $flag = StaffHrSuspiciousActivityFlag::query()->create([
            'staff_user_id' => $data['staff_user_id'],
            'staff_session_log_id' => $data['staff_session_log_id'] ?? null,
            'pattern' => $data['pattern'],
            'note' => $data['note'] ?? null,
            'flagged_by_user_id' => $actor->id,
            'flagged_at' => now(),
        ]);

        $this->auditTrail->log(
            actor: $actor,
            actionType: 'hr.suspicious_activity.flagged',
            targetStaffUserId: (int) $data['staff_user_id'],
            after: $flag->toArray(),
            request: $request,
        );

        $staff = User::query()->find($data['staff_user_id']);
        if ($staff !== null) {
            $this->staffNotifications->notifySuspiciousActivityFlagged(
                $staff,
                $data['pattern'],
                $data['note'] ?? null,
            );
        }

        return redirect()->route('admin.hr.index')->with('success', __('Suspicious activity flag logged.'));
    }

    public function markAlertRead(StaffHrAlert $alert): RedirectResponse
    {
        if ($alert->read_at === null) {
            $alert->update(['read_at' => now()]);
        }

        return redirect()->route('admin.hr.index')->with('success', __('Alert marked as read.'));
    }

    public function exportAlerts(): StreamedResponse
    {
        return AdminCsv::download('hr-alerts-'.now()->format('Y-m-d-His').'.csv', [
            'id',
            'staff_user_id',
            'alert_type',
            'severity',
            'message',
            'triggered_at',
            'read_at',
        ], function ($out): void {
            StaffHrAlert::query()
                ->orderByDesc('triggered_at')
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->id,
                            $row->staff_user_id,
                            $row->alert_type,
                            $row->severity,
                            $row->message,
                            $row->triggered_at?->toIso8601String(),
                            $row->read_at?->toIso8601String(),
                        ]);
                    }
                });
        });
    }

    public function exportAttendance(): StreamedResponse
    {
        return AdminCsv::download('hr-attendance-'.now()->format('Y-m-d-His').'.csv', [
            'id',
            'staff_user_id',
            'login_at',
            'logout_at',
            'duration_seconds',
            'active_seconds',
            'idle_seconds',
            'actions_count',
        ], function ($out): void {
            DB::table('staff_session_logs')
                ->orderByDesc('login_at')
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->id,
                            $row->staff_user_id,
                            $row->login_at,
                            $row->logout_at,
                            $row->duration_seconds,
                            $row->active_seconds,
                            $row->idle_seconds,
                            $row->actions_count,
                        ]);
                    }
                });
        });
    }

    public function exportPerformanceReport(User $staff): SymfonyResponse
    {
        $scores = DB::table('staff_performance_scores')
            ->where('staff_user_id', $staff->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(12)
            ->get();

        $html = view('pdf.hr-performance-report', [
            'staff' => $staff,
            'scores' => $scores,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return $pdf->download('hr-performance-report-'.$staff->id.'.pdf');
    }

    public function exportPayrollHistory(User $staff): SymfonyResponse
    {
        $profile = StaffPayrollProfile::query()->where('staff_user_id', $staff->id)->first();
        $adjustments = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->latest('effective_date')
            ->limit(24)
            ->get();
        $payslips = DB::table('staff_payslips')
            ->where('staff_user_id', $staff->id)
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->limit(24)
            ->get();

        $html = view('pdf.hr-payroll-history', [
            'staff' => $staff,
            'profile' => $profile,
            'adjustments' => $adjustments,
            'payslips' => $payslips,
            'generatedAt' => now(),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('a4');

        return $pdf->download('hr-payroll-history-'.$staff->id.'.pdf');
    }

    public function downloadPayslip(StaffPayslip $payslip): SymfonyResponse|RedirectResponse
    {
        $staff = User::query()->findOrFail($payslip->staff_user_id);
        $monthStart = CarbonImmutable::createFromDate((int) $payslip->year, (int) $payslip->month, 1)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $allowances = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'bonus')
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query
                    ->where(function ($inner) use ($monthEnd): void {
                        $inner->where('is_recurring', true)->whereDate('effective_date', '<=', $monthEnd->toDateString());
                    })
                    ->orWhere(function ($inner) use ($monthStart, $monthEnd): void {
                        $inner->where('is_recurring', false)->whereBetween('effective_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->orderBy('reference')
            ->get();

        $deductions = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'deduction')
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query
                    ->where(function ($inner) use ($monthEnd): void {
                        $inner->where('is_recurring', true)->whereDate('effective_date', '<=', $monthEnd->toDateString());
                    })
                    ->orWhere(function ($inner) use ($monthStart, $monthEnd): void {
                        $inner->where('is_recurring', false)->whereBetween('effective_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->orderBy('reference')
            ->get();

        $html = view('pdf.hr-payslip', [
            'staff' => $staff,
            'payslip' => $payslip,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'monthLabel' => $monthStart->format('F Y'),
            'staffCode' => $staff->uid ?: (string) $staff->id,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download('staff-payslip-'.$staff->id.'-'.$payslip->year.'-'.$payslip->month.'.pdf');
    }

    public function downloadStaffPayslip(Request $request, User $staff): SymfonyResponse
    {
        $year = max(2020, min(2100, (int) $request->integer('year', now()->year)));
        $month = max(1, min(12, (int) $request->integer('month', now()->month)));
        $monthStart = CarbonImmutable::createFromDate($year, $month, 1)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();

        $profile = StaffPayrollProfile::query()
            ->where('staff_user_id', $staff->id)
            ->whereDate('effective_from', '<=', $monthEnd->toDateString())
            ->first();
        $baseSalary = (float) ($profile?->base_salary ?? 0);

        $allowances = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'bonus')
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query->where(function ($inner) use ($monthEnd): void {
                    $inner->where('is_recurring', true)->whereDate('effective_date', '<=', $monthEnd->toDateString());
                })->orWhere(function ($inner) use ($monthStart, $monthEnd): void {
                    $inner->where('is_recurring', false)->whereBetween('effective_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                });
            })
            ->orderBy('reference')
            ->get();

        $deductions = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'deduction')
            ->where(function ($query) use ($monthStart, $monthEnd): void {
                $query->where(function ($inner) use ($monthEnd): void {
                    $inner->where('is_recurring', true)->whereDate('effective_date', '<=', $monthEnd->toDateString());
                })->orWhere(function ($inner) use ($monthStart, $monthEnd): void {
                    $inner->where('is_recurring', false)->whereBetween('effective_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                });
            })
            ->orderBy('reference')
            ->get();

        $totalAllowances = (float) $allowances->sum('amount');
        $totalDeductions = (float) $deductions->sum(function (StaffPayrollAdjustment $item) use ($baseSalary, $totalAllowances): float {
            if (($item->deduction_mode ?? 'flat') !== 'percentage') {
                return (float) $item->amount;
            }

            $percent = ((float) ($item->deduction_percentage ?? 0)) / 100;
            $basis = $item->deduction_basis ?? 'basic_salary';
            $base = match ($basis) {
                'total_pay' => $baseSalary + $totalAllowances,
                'custom_amount' => (float) ($item->deduction_custom_base_amount ?? 0),
                default => $baseSalary,
            };

            return round($base * $percent, 2);
        });

        $gross = $baseSalary + $totalAllowances;
        $net = $gross - $totalDeductions;

        $payslip = (object) [
            'year' => $year,
            'month' => $month,
            'gross_pay' => $gross,
            'bonuses' => $totalAllowances,
            'deductions' => $totalDeductions,
            'net_pay' => $net,
        ];

        $html = view('pdf.hr-payslip', [
            'staff' => $staff,
            'payslip' => $payslip,
            'allowances' => $allowances,
            'deductions' => $deductions,
            'monthLabel' => $monthStart->format('F Y'),
            'staffCode' => $staff->uid ?: (string) $staff->id,
        ])->render();

        return Pdf::loadHTML($html)->setPaper('a4')->download('staff-payslip-'.$staff->id.'-'.$year.'-'.$month.'.pdf');
    }

    public function payrollMonthlyIndex(Request $request): Response
    {
        $year = (int) $request->query('year', now()->year);
        $rows = StaffPayslip::query()
            ->with('staff:id,name,email')
            ->where('year', $year)
            ->orderByDesc('month')
            ->orderBy('staff_user_id')
            ->get()
            ->map(fn (StaffPayslip $slip) => [
                'id' => $slip->id,
                'staff' => $slip->staff ? ['id' => $slip->staff->id, 'name' => $slip->staff->name, 'email' => $slip->staff->email] : null,
                'year' => $slip->year,
                'month' => $slip->month,
                'gross_pay' => (float) $slip->gross_pay,
                'bonuses' => (float) $slip->bonuses,
                'deductions' => (float) $slip->deductions,
                'net_pay' => (float) $slip->net_pay,
            ])
            ->values()
            ->all();

        return Inertia::render('Admin/Hr/PayrollMonthly', [
            'year' => $year,
            'rows' => $rows,
        ]);
    }

    public function payrollMonthlyExport(Request $request): StreamedResponse
    {
        $year = (int) $request->query('year', now()->year);

        return AdminCsv::download('hr-monthly-payroll-'.$year.'-'.now()->format('Y-m-d-His').'.csv', [
            'staff_id',
            'staff_name',
            'staff_email',
            'year',
            'month',
            'gross_pay',
            'bonuses',
            'deductions',
            'net_pay',
        ], function ($out) use ($year): void {
            StaffPayslip::query()
                ->with('staff:id,name,email')
                ->where('year', $year)
                ->orderByDesc('month')
                ->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $row) {
                        fputcsv($out, [
                            $row->staff_user_id,
                            $row->staff?->name,
                            $row->staff?->email,
                            $row->year,
                            $row->month,
                            $row->gross_pay,
                            $row->bonuses,
                            $row->deductions,
                            $row->net_pay,
                        ]);
                    }
                });
        });
    }

    private function roleGroupLabel(string $group): string
    {
        return match ($group) {
            'group_a_chat_communications' => 'Group A - Chat & Communications',
            'group_b_moderation_operations' => 'Group B - Moderation Operations',
            'group_c_people_trust_management' => 'Group C - People & Trust Management',
            'group_d_financial_disputes_casework' => 'Group D - Financial, Disputes & Casework',
            default => $group,
        };
    }

    private function supportsAdvancedDeductionColumns(): bool
    {
        return Schema::hasColumns('staff_payroll_adjustments', [
            'deduction_mode',
            'deduction_basis',
            'deduction_percentage',
            'deduction_custom_base_amount',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveDeductionAmount(array $data): float
    {
        $mode = (string) ($data['deduction_mode'] ?? 'flat');
        if ($mode === 'flat') {
            return (float) ($data['amount'] ?? 0);
        }

        $staffUserId = (int) ($data['staff_user_id'] ?? 0);
        $percentage = ((float) ($data['deduction_percentage'] ?? 0)) / 100;
        if ($percentage <= 0 || $staffUserId <= 0) {
            return 0.0;
        }

        $profile = StaffPayrollProfile::query()->where('staff_user_id', $staffUserId)->first();
        $basicSalary = (float) ($profile?->base_salary ?? 0);
        $allowances = (float) StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staffUserId)
            ->where('type', 'bonus')
            ->where('is_recurring', true)
            ->sum('amount');

        $basis = (string) ($data['deduction_basis'] ?? 'basic_salary');
        $baseAmount = match ($basis) {
            'total_pay' => $basicSalary + $allowances,
            'custom_amount' => (float) ($data['deduction_custom_base_amount'] ?? 0),
            default => $basicSalary,
        };

        return max(0, round($baseAmount * $percentage, 2));
    }
}
