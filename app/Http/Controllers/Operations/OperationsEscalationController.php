<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffEscalationRequest;
use App\Models\AdminTask;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Http\RedirectResponse;

class OperationsEscalationController extends Controller
{
    public function __invoke(StoreStaffEscalationRequest $request, AdminActivityLogger $logger): RedirectResponse
    {
        $data = $request->validated();
        $superAdmin = User::query()
            ->whereHas('role', fn ($query) => $query->where('slug', 'super_admin'))
            ->orderBy('id')
            ->first();

        $task = AdminTask::query()->create([
            'created_by_admin_id' => $request->user()->id,
            'assigned_to_admin_id' => $superAdmin?->id,
            'source_type' => $data['subject_type'],
            'source_id' => $data['subject_id'] ?? null,
            'title' => '[Escalation] '.$data['title'],
            'description' => trim($data['recommendation'].(filled($data['context_url'] ?? null) ? "\n\nContext: ".$data['context_url'] : '')),
            'priority' => $data['priority'],
            'status' => 'todo',
            'due_at' => $data['priority'] === 'critical' ? now()->toDateString() : now()->addDay()->toDateString(),
        ]);

        $logger->log(
            actor: $request->user(),
            action: 'operations.escalation.created',
            subjectType: AdminTask::class,
            subjectId: $task->id,
            properties: [
                'title' => $task->title,
                'priority' => $task->priority,
                'source_type' => $task->source_type,
                'source_id' => $task->source_id,
            ],
            request: $request,
        );

        return back()->with('success', __('Escalation sent to Super Admin queue.'));
    }
}
