<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Services\Operations\StaffOperationsDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsTasksController extends Controller
{
    public function index(Request $request, StaffOperationsDashboardService $dashboard): Response
    {
        return Inertia::render('Operations/Tasks/Index', [
            'summary' => $dashboard->personalWorkload($request->user()),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $user = $request->user();
        $quick = (string) $request->input('quick', '');

        $paginator = AdminTask::query()
            ->with(['creator:id,name,email'])
            ->where('assigned_to_admin_id', $user->id)
            ->when($quick === 'overdue', fn ($query) => $query->whereNotNull('due_at')->where('due_at', '<', now()->toDateString())->where('status', '<>', 'done'))
            ->latest()
            ->paginate(min(100, max(25, $request->integer('per_page', 50))));

        return response()->json([
            'items' => collect($paginator->items())->map(fn (AdminTask $task) => $this->taskRow($task))->values()->all(),
            'meta' => ['total' => $paginator->total()],
        ]);
    }

    public function detail(AdminTask $task, Request $request): JsonResponse
    {
        abort_unless((int) $task->assigned_to_admin_id === (int) $request->user()->id, 403);

        $task->load(['creator:id,name,email']);

        return response()->json([
            'task' => $this->taskRow($task, true),
            'source_url' => $this->sourceUrl($task),
        ]);
    }

    public function status(Request $request, AdminTask $task): JsonResponse|RedirectResponse
    {
        abort_unless((int) $task->assigned_to_admin_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done'],
        ]);

        $task->forceFill([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ])->save();

        if ($request->wantsJson()) {
            return response()->json(['message' => __('Task updated.')]);
        }

        return back()->with('success', __('Task updated.'));
    }

    private function taskRow(AdminTask $task, bool $expanded = false): array
    {
        $row = [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $expanded ? $task->description : str((string) $task->description)->limit(160)->toString(),
            'priority' => $task->priority,
            'status' => $task->status,
            'source_type' => $task->source_type ? class_basename($task->source_type) : null,
            'source_id' => $task->source_id,
            'due_at' => $task->due_at?->toDateString(),
            'overdue' => $task->due_at && $task->status !== 'done' && $task->due_at->isPast(),
            'creator' => $task->creator?->name,
            'completed_at' => $task->completed_at?->toIso8601String(),
        ];

        if ($expanded) {
            $row['description_full'] = $task->description;
        }

        return $row;
    }

    private function sourceUrl(AdminTask $task): ?string
    {
        if (! $task->source_type || ! $task->source_id) {
            return null;
        }

        $type = class_basename($task->source_type);

        return match ($type) {
            'Quest' => route('operations.payments.index', ['q' => $task->source_id]),
            'UserVerification' => route('operations.verifications.index'),
            'QuestDispute' => route('operations.disputes.index'),
            'Review' => route('operations.reviews.index'),
            default => route('operations.dashboard'),
        };
    }
}
