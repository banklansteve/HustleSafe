<?php

namespace App\Services\Hr;

use App\Enums\StaffLeaveRequestStatus;
use App\Models\StaffLeaveRequest;
use App\Models\StaffNotificationPreference;
use App\Models\User;
use App\Notifications\StaffHrImpactMailNotification;
use App\Services\Operations\StaffNotificationCentreService;
use App\Support\Hr\StaffRoleGroupLabels;
use Illuminate\Support\Str;

class StaffHrImpactNotificationService
{
    public function __construct(
        private readonly StaffNotificationCentreService $notificationCentre,
    ) {}

    /**
     * @param  list<string>  $selectedGroups
     * @param  list<array<string, mixed>>  $created
     * @param  list<string>  $revoked
     */
    public function notifyRoleAssignmentChanged(
        User $staff,
        array $selectedGroups,
        array $created,
        array $revoked,
        string $startsOn,
        ?string $endsOn,
    ): void {
        $parts = [];

        foreach ($created as $row) {
            $group = (string) ($row['role_group'] ?? '');
            if ($group !== '') {
                $parts[] = 'Assigned: '.StaffRoleGroupLabels::label($group);
            }
        }

        foreach ($revoked as $group) {
            $parts[] = 'Removed: '.StaffRoleGroupLabels::label((string) $group);
        }

        if ($parts === [] && $selectedGroups !== []) {
            $labels = array_map(
                fn (string $group) => StaffRoleGroupLabels::label($group),
                $selectedGroups,
            );
            $parts[] = 'Active roles: '.implode(', ', $labels);
        }

        $range = $this->formatDate($startsOn);
        if ($endsOn) {
            $range .= ' to '.$this->formatDate($endsOn);
        } else {
            $range .= ' (open-ended)';
        }

        $body = implode('. ', $parts);
        if ($body !== '') {
            $body .= '. ';
        }
        $body .= 'Effective from '.$range.'.';

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:role:'.$staff->id.':'.now()->format('YmdHis'),
            title: 'Role assignment updated',
            body: $body,
            actionLabel: 'Open dashboard',
            actionUrl: route('operations.dashboard'),
            priority: 'high',
            mailSubject: 'Your role assignment was updated',
            mailLines: array_merge($parts, ['Effective from '.$range.'.']),
            data: ['event' => 'hr_update', 'kind' => 'role_assignment'],
        );
    }

    public function notifyLeaveRequestReviewed(StaffLeaveRequest $leaveRequest): void
    {
        $staff = $leaveRequest->staff;
        if ($staff === null) {
            return;
        }

        $isApproved = $leaveRequest->status === StaffLeaveRequestStatus::Approved->value;
        $statusLabel = $isApproved ? 'approved' : 'rejected';
        $dateRange = $this->formatDate($leaveRequest->start_date?->toDateString())
            .' to '
            .$this->formatDate($leaveRequest->end_date?->toDateString());
        $reviewNote = trim((string) ($leaveRequest->review_note ?? '')) ?: 'No note provided.';

        $body = sprintf(
            'Your %s leave request (%s) was %s. Note: %s',
            ucfirst((string) $leaveRequest->leave_type),
            $dateRange,
            $statusLabel,
            $reviewNote,
        );

        $mailLines = [
            'Your leave request has been reviewed.',
            'Status: '.strtoupper($statusLabel),
            'Leave type: '.ucfirst((string) $leaveRequest->leave_type),
            'Date range: '.$dateRange,
            'Review note: '.$reviewNote,
        ];

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:leave:'.$leaveRequest->id.':'.$leaveRequest->status,
            title: $isApproved ? 'Leave request approved' : 'Leave request rejected',
            body: $body,
            actionLabel: 'View leave',
            actionUrl: route('operations.account.index', ['tab' => 'leave']),
            priority: 'high',
            mailSubject: 'Leave request update',
            mailLines: $mailLines,
            data: [
                'event' => 'hr_update',
                'kind' => 'leave_review',
                'leave_request_id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
            ],
        );
    }

    public function notifyLeaveBalanceChanged(
        User $staff,
        string $leaveType,
        string $mode,
        int $days,
        int $year,
        string $reason,
    ): void {
        $action = match ($mode) {
            'allocate' => 'allocated',
            'add' => 'increased by',
            default => 'reduced by',
        };

        $body = sprintf(
            'Your %s leave balance for %d was %s %d day(s). Reason: %s',
            ucfirst($leaveType),
            $year,
            $action,
            $days,
            $reason,
        );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:leave_balance:'.$staff->id.':'.$year.':'.$leaveType.':'.now()->format('YmdHis'),
            title: 'Leave balance updated',
            body: $body,
            actionLabel: 'View leave',
            actionUrl: route('operations.account.index', ['tab' => 'leave']),
            priority: 'normal',
            mailSubject: 'Your leave balance was updated',
            mailLines: [$body],
            data: [
                'event' => 'hr_update',
                'kind' => 'leave_balance',
                'leave_type' => $leaveType,
                'year' => $year,
            ],
        );
    }

    public function notifyPayrollProfileUpdated(User $staff, float $baseSalary, string $effectiveFrom): void
    {
        $body = sprintf(
            'Your payroll profile was updated. Base salary: NGN %s. Effective from %s.',
            number_format($baseSalary, 2),
            $this->formatDate($effectiveFrom),
        );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:payroll_profile:'.$staff->id.':'.now()->format('YmdHis'),
            title: 'Payroll profile updated',
            body: $body,
            actionLabel: 'View payroll',
            actionUrl: route('operations.account.index', ['tab' => 'payroll']),
            priority: 'normal',
            mailSubject: 'Your payroll profile was updated',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'payroll_profile'],
        );
    }

    public function notifyPayrollAdjustmentCreated(User $staff, string $type, float $amount, string $reason, string $effectiveDate): void
    {
        $label = Str::headline($type);
        $body = sprintf(
            'A %s of NGN %s was logged on your payroll (%s). Reason: %s',
            strtolower($label),
            number_format($amount, 2),
            $this->formatDate($effectiveDate),
            $reason,
        );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:payroll_adjustment:'.$staff->id.':'.now()->format('YmdHisu'),
            title: 'Payroll adjustment logged',
            body: $body,
            actionLabel: 'View payroll',
            actionUrl: route('operations.account.index', ['tab' => 'payroll']),
            priority: 'normal',
            mailSubject: 'Payroll adjustment on your account',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'payroll_adjustment', 'type' => $type],
        );
    }

    public function notifyPayrollAllowanceChanged(User $staff, string $title, float $amount, string $action, string $effectiveFrom): void
    {
        $verb = match ($action) {
            'created' => 'added',
            'updated' => 'updated',
            default => 'removed',
        };

        $body = $action === 'deleted'
            ? sprintf('Allowance "%s" was removed from your payroll.', $title)
            : sprintf(
                'Allowance "%s" was %s (NGN %s, effective %s).',
                $title,
                $verb,
                number_format($amount, 2),
                $this->formatDate($effectiveFrom),
            );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:payroll_allowance:'.$staff->id.':'.$action.':'.now()->format('YmdHisu'),
            title: 'Payroll allowance '.$verb,
            body: $body,
            actionLabel: 'View payroll',
            actionUrl: route('operations.account.index', ['tab' => 'payroll']),
            priority: 'normal',
            mailSubject: 'Payroll allowance update',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'payroll_allowance', 'action' => $action],
        );
    }

    public function notifyPayrollDeductionChanged(User $staff, string $title, string $action, ?string $effectiveFrom = null): void
    {
        $verb = match ($action) {
            'created' => 'added',
            'updated' => 'updated',
            default => 'removed',
        };

        $body = $action === 'deleted'
            ? sprintf('Deduction "%s" was removed from your payroll.', $title)
            : sprintf(
                'Deduction "%s" was %s%s.',
                $title,
                $verb,
                $effectiveFrom ? ' (effective '.$this->formatDate($effectiveFrom).')' : '',
            );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:payroll_deduction:'.$staff->id.':'.$action.':'.now()->format('YmdHisu'),
            title: 'Payroll deduction '.$verb,
            body: $body,
            actionLabel: 'View payroll',
            actionUrl: route('operations.account.index', ['tab' => 'payroll']),
            priority: 'normal',
            mailSubject: 'Payroll deduction update',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'payroll_deduction', 'action' => $action],
        );
    }

    public function notifyComplianceCaseOpened(User $staff, string $severity, string $incidentNote): void
    {
        $body = sprintf(
            'A %s compliance case was opened regarding your account. Note: %s',
            $severity,
            Str::limit($incidentNote, 240),
        );

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:compliance:'.$staff->id.':opened:'.now()->format('YmdHis'),
            title: 'Compliance case opened',
            body: $body,
            actionLabel: 'View notifications',
            actionUrl: route('operations.notifications.index'),
            priority: $severity === 'critical' ? 'critical' : 'high',
            mailSubject: 'Compliance case opened on your account',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'compliance_opened', 'severity' => $severity],
        );
    }

    public function notifyComplianceCaseStatusUpdated(User $staff, string $status, ?string $note): void
    {
        $body = 'Your compliance case status was updated to '.Str::headline($status).'.';
        if ($note) {
            $body .= ' Note: '.Str::limit($note, 240);
        }

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:compliance:'.$staff->id.':status:'.$status.':'.now()->format('YmdHis'),
            title: 'Compliance case updated',
            body: $body,
            actionLabel: 'View notifications',
            actionUrl: route('operations.notifications.index'),
            priority: 'high',
            mailSubject: 'Compliance case status update',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'compliance_status', 'status' => $status],
        );
    }

    public function notifySuspiciousActivityFlagged(User $staff, string $pattern, ?string $note): void
    {
        $body = 'Suspicious activity was flagged on your account: '.Str::headline(str_replace('_', ' ', $pattern)).'.';
        if ($note) {
            $body .= ' Note: '.Str::limit($note, 240);
        }

        $this->deliver(
            staff: $staff,
            dedupeKey: 'hr:suspicious:'.$staff->id.':'.now()->format('YmdHis'),
            title: 'Account activity flagged',
            body: $body,
            actionLabel: 'View notifications',
            actionUrl: route('operations.notifications.index'),
            priority: 'high',
            mailSubject: 'Activity flagged on your staff account',
            mailLines: [$body],
            data: ['event' => 'hr_update', 'kind' => 'suspicious_activity', 'pattern' => $pattern],
        );
    }

    /**
     * @param  list<string>  $mailLines
     * @param  array<string, mixed>  $data
     */
    private function deliver(
        User $staff,
        string $dedupeKey,
        string $title,
        string $body,
        string $actionLabel,
        string $actionUrl,
        string $priority,
        string $mailSubject,
        array $mailLines,
        array $data = [],
    ): void {
        if ($staff->role?->slug !== 'admin') {
            return;
        }

        $this->notificationCentre->notifyHrImpact(
            staff: $staff,
            dedupeKey: $dedupeKey,
            title: $title,
            body: $body,
            actionLabel: $actionLabel,
            actionUrl: $actionUrl,
            priority: $priority,
            data: $data,
        );

        if ($this->wantsEmail($staff)) {
            $staff->notify(new StaffHrImpactMailNotification(
                subject: $mailSubject,
                lines: $mailLines,
                actionUrl: $actionUrl,
                actionText: $actionLabel,
            ));
        }
    }

    private function wantsEmail(User $staff): bool
    {
        $defaults = config('operations.notification_events.hr_update', []);
        $stored = StaffNotificationPreference::query()
            ->where('staff_user_id', $staff->id)
            ->value('preferences');

        return (bool) data_get($stored, 'hr_update.email', $defaults['default_email'] ?? true);
    }

    private function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        try {
            return \Illuminate\Support\Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }
}
