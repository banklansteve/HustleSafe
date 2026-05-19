<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAdminTaskRequest;
use App\Http\Requests\Admin\UpdateAdminTaskStatusRequest;
use App\Models\AdminTask;
use App\Services\Admin\AdminCommandCentreService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTaskController extends Controller
{
    public function index(Request $request, AdminCommandCentreService $service): Response
    {
        return Inertia::render('Admin/CommandRisk/Index', [
            'mode' => 'tasks',
            'payload' => $service->taskPayload($request->user()),
        ]);
    }

    public function store(StoreAdminTaskRequest $request): RedirectResponse
    {
        AdminTask::query()->create([
            ...$request->validated(),
            'created_by_admin_id' => $request->user()->id,
        ]);

        return back()->with('success', 'Task created and assigned.');
    }

    public function status(UpdateAdminTaskStatusRequest $request, AdminTask $task): RedirectResponse
    {
        $status = $request->validated('status');
        $task->forceFill([
            'status' => $status,
            'completed_at' => $status === 'done' ? now() : null,
        ])->save();

        return back()->with('success', 'Task status updated.');
    }
}
