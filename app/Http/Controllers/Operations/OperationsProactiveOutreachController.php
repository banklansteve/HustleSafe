<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operations\ProactiveOutreachContactRequest;
use App\Models\StaffProactiveOutreachItem;
use App\Models\StaffResponseTemplate;
use App\Services\Operations\ProactiveOutreachQueueService;
use App\Services\Operations\StaffResponseTemplateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsProactiveOutreachController extends Controller
{
    public function __construct(
        private readonly ProactiveOutreachQueueService $queue,
        private readonly StaffResponseTemplateService $templates,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Operations/Outreach/Index', [
            'can_manage_templates' => request()->user()?->role?->slug === 'super_admin',
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->queue->listing($request));
    }

    public function detail(StaffProactiveOutreachItem $item): JsonResponse
    {
        return response()->json($this->queue->detail($item));
    }

    public function assign(Request $request, StaffProactiveOutreachItem $item): JsonResponse
    {
        $this->queue->assign($request->user(), $item, $request);

        return response()->json(['message' => 'Assigned to you.']);
    }

    public function snooze(Request $request, StaffProactiveOutreachItem $item): JsonResponse
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:30'],
        ]);

        $this->queue->snooze($request->user(), $item, $data, $request);

        return response()->json(['message' => 'Snoozed.']);
    }

    public function resolve(Request $request, StaffProactiveOutreachItem $item): JsonResponse
    {
        $data = $request->validate([
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->queue->resolve($request->user(), $item, $data, $request);

        return response()->json(['message' => 'Marked resolved.']);
    }

    public function outreach(ProactiveOutreachContactRequest $request, StaffProactiveOutreachItem $item): JsonResponse
    {
        $this->queue->outreach($request->user(), $item, $request->validated(), $request);

        return response()->json(['message' => 'Outreach sent.']);
    }

    public function templatePreview(Request $request, StaffResponseTemplate $template): JsonResponse
    {
        $itemUuid = $request->input('item_uuid');
        $item = $itemUuid
            ? StaffProactiveOutreachItem::query()->where('uuid', $itemUuid)->firstOrFail()
            : null;

        $rendered = $item
            ? $this->templates->renderForItem($template, $item)
            : $this->templates->render($template, ['name' => 'there', 'staff_name' => $request->user()?->name ?? 'HustleSafe Support']);

        return response()->json([
            'template' => $this->templates->row($template),
            'preview' => $rendered,
        ]);
    }
}
