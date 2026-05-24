<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminQuestCompletionEventsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminQuestCompletionEventsController extends Controller
{
    public function __construct(private readonly AdminQuestCompletionEventsService $events) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $payload = $this->events->index($request);

        return Inertia::render('Admin/QuestCompletionEvents/Index', $payload);
    }
}
