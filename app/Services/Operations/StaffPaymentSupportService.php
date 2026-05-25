<?php

namespace App\Services\Operations;

use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\StaffPaymentException;
use App\Models\User;
use App\Services\AdminActivityLogger;
use App\Support\NgnMoney;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StaffPaymentSupportService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(Request $request): LengthAwarePaginator
    {
        $escrow = trim((string) $request->input('escrow', ''));
        $q = trim((string) $request->input('q', ''));

        $query = Quest::query()
            ->with(['client:id,name,email', 'freelancer:id,name,email']);

        if ($escrow !== '') {
            $query->where('escrow_status', $escrow);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('reference_code', 'like', "%{$q}%");
            });
        }

        return $query->orderByDesc('id')
            ->paginate(min(100, max(25, $request->integer('per_page', 50))))
            ->withQueryString()
            ->through(fn (Quest $quest) => $this->row($quest));
    }

    public function detail(Quest $quest): array
    {
        $quest->load(['client:id,name,email', 'freelancer:id,name,email']);

        return [
            'quest' => $this->row($quest, true),
            'allowed_requests' => config('operations.payment_request_types', []),
            'permissions' => [
                'can_approve_payout' => false,
                'can_hold_release_directly' => false,
            ],
        ];
    }

    public function requestAction(Quest $quest, User $staff, array $data, Request $request): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            throw ValidationException::withMessages(['request' => 'Task system unavailable.']);
        }

        $superAdmin = User::query()
            ->whereHas('role', fn ($q) => $q->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first();

        if ($superAdmin === null) {
            throw ValidationException::withMessages(['request' => 'No Super Admin available to review this request.']);
        }

        $type = $data['type'];
        $titles = [
            'hold_payout' => 'Hold payout',
            'release_payout' => 'Release payout',
            'refund' => 'Refund request',
        ];

        $task = AdminTask::query()->create([
            'created_by_admin_id' => $staff->id,
            'assigned_to_admin_id' => $superAdmin->id,
            'source_type' => Quest::class,
            'source_id' => $quest->id,
            'title' => ($titles[$type] ?? 'Payment request').' · '.$quest->reference_code,
            'description' => trim($data['reason']),
            'priority' => 'high',
            'status' => 'todo',
            'due_at' => now()->addDay(),
        ]);

        if (Schema::hasTable('staff_payment_exceptions')) {
            StaffPaymentException::query()->create([
                'staff_user_id' => $staff->id,
                'user_id' => $quest->freelancer_id,
                'quest_id' => $quest->id,
                'admin_task_id' => $task->id,
                'type' => $type,
                'status' => 'pending_approval',
                'amount_minor' => $quest->paid_out_minor ?? $quest->budget_amount_minor,
                'staff_summary' => trim($data['reason']),
                'error_summary' => 'Awaiting Super Admin approval',
            ]);
        }

        $this->logger->log($staff, 'operations.payment.'.$type.'_requested', Quest::class, $quest->id, $data, $request);
    }

    private function row(Quest $quest, bool $expanded = false): array
    {
        $paidOutMinor = (int) ($quest->paid_out_minor ?? 0);
        $budgetMinor = (int) ($quest->budget_amount_minor ?? 0);

        $base = [
            'id' => $quest->id,
            'route_key' => $quest->getRouteKey(),
            'reference_code' => $quest->reference_code,
            'title' => $quest->title,
            'status' => $quest->status?->value ?? (string) $quest->status,
            'escrow_status' => $quest->escrow_status,
            'budget_amount_minor' => $budgetMinor,
            'budget_display' => NgnMoney::format($budgetMinor),
            'paid_out_minor' => $paidOutMinor,
            'paid_out_display' => NgnMoney::format($paidOutMinor),
            'client' => $quest->client?->name,
            'freelancer' => $quest->freelancer?->name,
            'escrow_funded_at' => $quest->escrow_funded_at?->toIso8601String(),
        ];

        if (! $expanded) {
            return $base;
        }

        return array_merge($base, [
            'payout_status' => $quest->payout_status ?? null,
            'payout_failure_reason' => $quest->payout_failure_reason ?? null,
            'payout_queued_at' => $quest->payout_queued_at ?? null,
            'client_email' => $quest->client?->email,
            'freelancer_email' => $quest->freelancer?->email,
        ]);
    }
}
