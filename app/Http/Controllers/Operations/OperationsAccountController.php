<?php

namespace App\Http\Controllers\Operations;

use App\Enums\StaffLeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Account\AccountDetailsUpdateRequest;
use App\Http\Requests\Account\AccountVisibilityUpdateRequest;
use App\Http\Requests\Account\AvatarUploadRequest;
use App\Http\Requests\Operations\StoreStaffLeaveRequest;
use App\Models\StaffLeaveBalance;
use App\Models\StaffLeaveRequest;
use App\Models\StaffPayrollAdjustment;
use App\Models\StaffPayrollProfile;
use App\Models\StaffPayslip;
use App\Models\State;
use App\Services\CloudinaryAvatarService;
use App\Services\TrustScoreOrchestrator;
use App\Support\TextCasing;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsAccountController extends Controller
{
    public function show(Request $request): Response
    {
        $staff = $request->user();
        $staff->loadMissing(['stateModel:id,name', 'localGovernmentModel:id,name']);
        $year = now()->year;

        $balance = StaffLeaveBalance::query()->firstOrCreate(
            ['staff_user_id' => $staff->id, 'year' => $year],
            ['annual_days' => 0, 'sick_days' => 0, 'emergency_days' => 0, 'unpaid_days' => 0]
        );

        $leaveRequests = StaffLeaveRequest::query()
            ->where('staff_user_id', $staff->id)
            ->latest('id')
            ->limit(20)
            ->get();

        $payrollProfile = StaffPayrollProfile::query()->where('staff_user_id', $staff->id)->first();
        $recurringAllowances = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'bonus')
            ->where('is_recurring', true)
            ->orderBy('reference')
            ->get();
        $recurringDeductions = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->where('type', 'deduction')
            ->where('is_recurring', true)
            ->orderBy('reference')
            ->get();
        $currentMonthAdjustments = StaffPayrollAdjustment::query()
            ->where('staff_user_id', $staff->id)
            ->whereDate('effective_date', '>=', now()->startOfMonth()->toDateString())
            ->whereDate('effective_date', '<=', now()->endOfMonth()->toDateString())
            ->latest('effective_date')
            ->get();
        $payslips = StaffPayslip::query()
            ->where('staff_user_id', $staff->id)
            ->latest('year')
            ->latest('month')
            ->limit(24)
            ->get();
        $teamLeaveCalendar = StaffLeaveRequest::query()
            ->where('status', StaffLeaveRequestStatus::Approved->value)
            ->whereDate('end_date', '>=', now()->toDateString())
            ->with('staff:id,name,email')
            ->orderBy('start_date')
            ->limit(80)
            ->get();

        $visibilityDefaults = config('profile.client_public_defaults', config('profile.public_defaults', []));
        $visibilityKeys = array_values(array_filter(array_keys($visibilityDefaults), fn (string $key): bool => ! in_array($key, ['show_phone', 'show_email'], true)));

        return Inertia::render('Operations/Account/Index', [
            'user' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'first_name' => $staff->first_name,
                'last_name' => $staff->last_name,
                'email' => $staff->email,
                'phone' => $staff->phone,
                'avatar_url' => $staff->avatar_url,
                'headline' => $staff->headline,
                'bio' => $staff->bio,
                'profession' => $staff->profession,
                'job_title' => $staff->job_title,
                'city' => $staff->city,
                'address_line' => $staff->address_line,
                'state_id' => $staff->state_id,
                'local_government_id' => $staff->local_government_id,
                'state_name' => $staff->stateModel?->name,
                'local_government_name' => $staff->localGovernmentModel?->name,
                'hide_online_presence' => (bool) $staff->hide_online_presence,
            ],
            'locations' => State::query()
                ->with(['localGovernments:id,state_id,name'])
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (State $state) => [
                    'id' => $state->id,
                    'name' => $state->name,
                    'local_governments' => $state->localGovernments->map(fn ($lg) => [
                        'id' => $lg->id,
                        'name' => $lg->name,
                        'state_id' => $lg->state_id,
                    ])->values()->all(),
                ])
                ->values()
                ->all(),
            'visibility' => $staff->effectivePublicProfileSettings(),
            'visibilityKeys' => $visibilityKeys,
            'avatarConfigured' => app(CloudinaryAvatarService::class)->isConfigured(),
            'balance' => $balance,
            'leaveRequests' => $leaveRequests,
            'payrollProfile' => $payrollProfile,
            'payrollAllowances' => $recurringAllowances,
            'payrollDeductions' => $recurringDeductions,
            'payslips' => $payslips,
            'currentMonthAdjustments' => $currentMonthAdjustments,
            'teamLeaveCalendar' => $teamLeaveCalendar,
        ]);
    }

    public function updateDetails(AccountDetailsUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $data = TextCasing::patchUserProfile(
            $request->validated(),
            ['first_name', 'last_name', 'name', 'headline', 'city', 'profession', 'job_title', 'company_name'],
            ['address_line', 'bio'],
        );

        $user->fill(array_filter($data, fn ($value) => $value !== null && $value !== ''));
        $user->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('operations.account.index')->with('success', __('Profile updated.'));
    }

    public function updateVisibility(AccountVisibilityUpdateRequest $request, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        $user = $request->user();
        $patch = $request->validatedSettings();
        $merged = array_merge($user->public_profile_settings ?? [], $patch);
        unset($merged['show_phone'], $merged['show_email']);

        $user->public_profile_settings = $merged;
        $user->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('operations.account.index')->with('success', __('Privacy settings saved.'));
    }

    public function updatePresence(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'hide_online_presence' => ['required', 'boolean'],
        ]);

        $request->user()->forceFill([
            'hide_online_presence' => $data['hide_online_presence'],
        ])->save();

        return redirect()->route('operations.account.index')->with('success', __('Online presence preference saved.'));
    }

    public function updateAvatar(AvatarUploadRequest $request, CloudinaryAvatarService $cloudinary, TrustScoreOrchestrator $trustScores): RedirectResponse
    {
        if (! $cloudinary->isConfigured()) {
            return redirect()
                ->route('operations.account.index')
                ->withErrors(['avatar' => __('Photo upload is not configured yet. Add Cloudinary credentials to your .env file.')]);
        }

        $user = $request->user();
        $url = $cloudinary->uploadAvatar($request->file('avatar'), $user->id);
        $user->forceFill(['avatar_url' => $url])->save();
        $trustScores->recalculate($user->fresh());

        return redirect()->route('operations.account.index')->with('success', __('Profile photo updated.'));
    }

    public function storeLeaveRequest(StoreStaffLeaveRequest $request): RedirectResponse
    {
        $staff = $request->user();
        $durationType = (string) $request->validated('duration_type');
        $startDate = CarbonImmutable::parse($request->validated('start_date'));
        $endDate = match ($durationType) {
            'multiple_days' => CarbonImmutable::parse($request->validated('end_date')),
            default => $startDate,
        };
        $days = match ($durationType) {
            'multiple_days' => max(2, $startDate->diffInDays($endDate) + 1),
            default => 1,
        };
        $hoursRequested = $durationType === 'hours' ? (int) $request->validated('hours_requested') : null;

        StaffLeaveRequest::query()->create([
            'staff_user_id' => $staff->id,
            'leave_type' => $request->validated('leave_type'),
            'duration_type' => $durationType,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days_requested' => $days,
            'hours_requested' => $hoursRequested,
            'reason' => $request->validated('reason'),
            'status' => StaffLeaveRequestStatus::Pending->value,
        ]);

        return redirect()->route('operations.account.index')->with('success', __('Leave request submitted for review.'));
    }

    public function downloadPayslip(StaffPayslip $payslip, Request $request)
    {
        abort_unless((int) $payslip->staff_user_id === (int) $request->user()->id, 403);
        $staff = $request->user();
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

    public function downloadPayslipByPeriod(Request $request)
    {
        $staff = $request->user();
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
}
