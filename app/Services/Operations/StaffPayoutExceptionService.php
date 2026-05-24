<?php

namespace App\Services\Operations;

use App\Models\AdminTask;
use App\Models\Quest;
use App\Models\StaffPaymentException;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class StaffPayoutExceptionService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(User $staff, Request $request): array
    {
        $this->syncFromTasks($staff);

        $filter = (string) $request->input('filter', 'open');

        $query = StaffPaymentException::query()
            ->with(['user:id,name,email', 'quest:id,title,reference_code'])
            ->where('staff_user_id', $staff->id);

        if ($filter === 'pending_super_admin') {
            $query->whereIn('type', ['hold_payout', 'release_payout', 'refund'])->where('status', 'pending_approval');
        } elseif ($filter === 'resolved') {
            $query->where('status', 'resolved');
        } else {
            $query->where('status', 'open');
        }

        return [
            'items' => $query->latest()->limit(100)->get()->map(fn (StaffPaymentException $row) => $this->row($row)),
        ];
    }

    public function detail(StaffPaymentException $exception, User $staff): array
    {
        abort_unless((int) $exception->staff_user_id === (int) $staff->id, 403);
        $exception->load(['user:id,name,email', 'quest.client:id,name,email', 'quest.freelancer:id,name,email', 'adminTask']);

        return [
            'exception' => $this->row($exception, true),
            'quest' => $exception->quest ? [
                'id' => $exception->quest->id,
                'title' => $exception->quest->title,
                'reference_code' => $exception->quest->reference_code,
                'escrow_status' => $exception->quest->escrow_status,
                'client' => $exception->quest->client?->only(['id', 'name', 'email']),
                'freelancer' => $exception->quest->freelancer?->only(['id', 'name', 'email']),
            ] : null,
        ];
    }

    public function record(User $staff, array $data, Request $request): StaffPaymentException
    {
        $quest = isset($data['quest_id']) ? Quest::query()->find($data['quest_id']) : null;

        $exception = StaffPaymentException::query()->create([
            'staff_user_id' => $staff->id,
            'user_id' => $data['user_id'] ?? $quest?->freelancer_id,
            'quest_id' => $quest?->id,
            'type' => $data['type'],
            'status' => 'open',
            'amount_minor' => $data['amount_minor'] ?? $quest?->paid_out_minor ?? $quest?->budget_amount_minor,
            'error_code' => $data['error_code'] ?? null,
            'error_summary' => $data['error_summary'] ?? null,
            'staff_summary' => $data['staff_summary'] ?? null,
            'metadata' => $data['metadata'] ?? [],
        ]);

        $this->logger->log($staff, 'operations.payout_exception.created', StaffPaymentException::class, $exception->id, $data, $request);

        return $exception;
    }

    public function resolve(StaffPaymentException $exception, User $staff, array $data, Request $request): void
    {
        abort_unless((int) $exception->staff_user_id === (int) $staff->id, 403);
        $exception->forceFill([
            'status' => 'resolved',
            'staff_summary' => $data['summary'] ?? $exception->staff_summary,
            'resolved_at' => now(),
        ])->save();

        $this->logger->log($staff, 'operations.payout_exception.resolved', StaffPaymentException::class, $exception->id, $data, $request);
    }

    public function linkTask(StaffPaymentException $exception, AdminTask $task): void
    {
        $exception->forceFill([
            'admin_task_id' => $task->id,
            'status' => 'pending_approval',
            'type' => match (true) {
                str_contains(strtolower($task->title), 'hold') => 'hold_payout',
                str_contains(strtolower($task->title), 'release') => 'release_payout',
                str_contains(strtolower($task->title), 'refund') => 'refund',
                default => $exception->type,
            },
        ])->save();
    }

    private function syncFromTasks(User $staff): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            return;
        }

        AdminTask::query()
            ->where('created_by_admin_id', $staff->id)
            ->where('source_type', Quest::class)
            ->where('status', '<>', 'done')
            ->latest()
            ->limit(40)
            ->get()
            ->each(function (AdminTask $task) use ($staff): void {
                $title = strtolower($task->title);
                if (! str_contains($title, 'payout') && ! str_contains($title, 'refund') && ! str_contains($title, 'hold') && ! str_contains($title, 'release')) {
                    return;
                }

                $type = str_contains($title, 'refund') ? 'refund' : (str_contains($title, 'hold') ? 'hold_payout' : (str_contains($title, 'release') ? 'release_payout' : 'failed_payout'));

                StaffPaymentException::query()->updateOrCreate(
                    ['admin_task_id' => $task->id],
                    [
                        'staff_user_id' => $staff->id,
                        'quest_id' => $task->source_id,
                        'type' => $type,
                        'status' => 'pending_approval',
                        'staff_summary' => $task->description,
                        'error_summary' => 'Awaiting Super Admin approval',
                    ],
                );
            });

        Quest::query()
            ->where(function ($q): void {
                $q->where('escrow_status', 'payout_failed')
                    ->orWhere('escrow_status', 'like', '%fail%');
            })
            ->latest()
            ->limit(20)
            ->get()
            ->each(function (Quest $quest) use ($staff): void {
                StaffPaymentException::query()->updateOrCreate(
                    [
                        'staff_user_id' => $staff->id,
                        'quest_id' => $quest->id,
                        'type' => 'failed_payout',
                    ],
                    [
                        'user_id' => $quest->freelancer_id,
                        'status' => 'open',
                        'amount_minor' => $quest->paid_out_minor ?? $quest->budget_amount_minor,
                        'error_summary' => 'Payout failed — communicate with the freelancer and escalate if needed.',
                    ],
                );
            });
    }

    private function row(StaffPaymentException $exception, bool $expanded = false): array
    {
        $base = [
            'id' => $exception->id,
            'uuid' => $exception->uuid,
            'type' => $exception->type,
            'status' => $exception->status,
            'amount_minor' => $exception->amount_minor,
            'error_summary' => $exception->error_summary,
            'user' => $exception->user?->name,
            'quest' => $exception->quest?->title,
            'quest_reference' => $exception->quest?->reference_code,
            'created_at' => $exception->created_at?->toIso8601String(),
        ];

        if (! $expanded) {
            return $base;
        }

        return array_merge($base, [
            'staff_summary' => $exception->staff_summary,
            'error_code' => $exception->error_code,
            'metadata' => $exception->metadata ?? [],
            'admin_task_id' => $exception->admin_task_id,
        ]);
    }
}
