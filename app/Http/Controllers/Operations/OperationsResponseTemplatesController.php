<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\StoreStaffResponseTemplateRequest;
use App\Http\Requests\Operations\UpdateStaffResponseTemplateRequest;
use App\Models\StaffResponseTemplate;
use App\Services\Operations\StaffResponseTemplateService;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class OperationsResponseTemplatesController extends Controller
{
    public function __construct(protected readonly StaffResponseTemplateService $service) {}

    public function index(): Response
    {
        abort_unless(request()->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Operations/ResponseTemplates/Index', [
            'situations' => $this->service->situationOptions(),
            'route_prefix' => 'operations',
        ]);
    }

    public function listing(): JsonResponse
    {
        abort_unless(request()->user()?->role?->slug === 'super_admin', 403);

        return response()->json([
            'items' => $this->service->listing(activeOnly: false),
            'situations' => $this->service->situationOptions(),
        ]);
    }

    public function store(StoreStaffResponseTemplateRequest $request): JsonResponse
    {
        $template = $this->service->create($request->user(), $request->validated());

        return response()->json([
            'message' => 'Template created.',
            'template' => $this->service->row($template),
        ]);
    }

    public function update(UpdateStaffResponseTemplateRequest $request, StaffResponseTemplate $template): JsonResponse
    {
        $updated = $this->service->update($template, $request->user(), $request->validated());

        return response()->json([
            'message' => 'Template updated.',
            'template' => $this->service->row($updated),
        ]);
    }
}
