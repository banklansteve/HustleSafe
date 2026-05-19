<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEmailBroadcastRequest;
use App\Http\Requests\Admin\StoreEmailBroadcastTemplateRequest;
use App\Models\EmailBroadcastTemplate;
use App\Services\Admin\EmailBroadcastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminEmailBroadcastController extends Controller
{
    public function index(Request $request, EmailBroadcastService $service): Response
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        return Inertia::render('Admin/Communications/Index', $service->indexPayload($request));
    }

    public function audience(Request $request, EmailBroadcastService $service): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $validated = $request->validate([
            'audience' => ['required', 'array'],
        ]);

        return response()->json($service->previewAudience($validated['audience']));
    }

    public function send(StoreEmailBroadcastRequest $request, EmailBroadcastService $service): JsonResponse
    {
        $broadcast = $service->createBroadcast($request->validated(), $request->user(), $request);

        return response()->json([
            'message' => $broadcast->status === 'scheduled' ? 'Email broadcast scheduled.' : 'Email broadcast queued for delivery.',
            'broadcast' => $broadcast->only(['id', 'subject', 'status', 'total_recipients', 'scheduled_for']),
        ]);
    }

    public function test(StoreEmailBroadcastRequest $request, EmailBroadcastService $service): JsonResponse
    {
        $service->sendTest($request->validated(), $request->user());

        return response()->json(['message' => 'Test email sent to your admin email address.']);
    }

    public function storeTemplate(StoreEmailBroadcastTemplateRequest $request, EmailBroadcastService $service): JsonResponse
    {
        $template = $service->createTemplate($request->validated(), $request->user());

        return response()->json(['message' => 'Template saved.', 'template' => $template]);
    }

    public function updateTemplate(StoreEmailBroadcastTemplateRequest $request, EmailBroadcastTemplate $template, EmailBroadcastService $service): JsonResponse
    {
        $template = $service->updateTemplate($template, $request->validated());

        return response()->json(['message' => 'Template updated.', 'template' => $template]);
    }

    public function destroyTemplate(Request $request, EmailBroadcastTemplate $template): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);
        abort_if($template->is_system, 403);
        $template->delete();

        return response()->json(['message' => 'Template deleted.']);
    }
}
