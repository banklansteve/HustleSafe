<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\AdminTask;
use App\Services\Operations\StaffOperationsDashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsTasksController extends Controller
{
    public function index(Request $request, StaffOperationsDashboardService $dashboard): Response
    {
        $user = $request->user();
        $quick = (string) $request->query('quick', '');

        $tasks = AdminTask::query()
            ->with(['creator:id,name,email'])
            ->where('assigned_to_admin_id', $user->id)
            ->when($quick === 'overdue', fn ($query) => $query->whereNotNull('due_at')->where('due_at', '<', now()->toDateString())->where('status', '<>', 'done'))
            ->latest()
            ->paginate(20)
            ->through(fn (AdminTask $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'description' => $task->description,
                'priority' => $task->priority,
                'status' => $task->status,
                'source_type' => $task->source_type ? class_basename($task->source_type) : null,
                'source_id' => $task->source_id,
                'due_at' => $task->due_at?->toDateString(),
                'creator' => $task->creator?->name,
            ])
            ->withQueryString();

        return Inertia::render('Operations/Tasks/Index', [
            'tasks' => $tasks,
            'summary' => $dashboard->personalWorkload($user),
            'quick' => $quick,
        ]);
    }

    public function status(Request $request, AdminTask $task): RedirectResponse
    {
        abort_unless((int) $task->assigned_to_admin_id === (int) $request->user()->id, 403);

        $data = $request->validate([
            'status' => ['required', 'in:todo,in_progress,done'],
        ]);

        $task->forceFill([
            'status' => $data['status'],
            'completed_at' => $data['status'] === 'done' ? now() : null,
        ])->save();

        return back()->with('success', __('Task updated.'));
    }
}
