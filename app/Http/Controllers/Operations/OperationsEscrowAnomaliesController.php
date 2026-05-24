<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\StaffEscrowAnomalyNote;
use App\Services\Operations\StaffEscrowAnomalyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsEscrowAnomaliesController extends Controller
{
    public function __construct(private readonly StaffEscrowAnomalyService $service) {}

    public function index(): Response
    {
        return Inertia::render('Operations/EscrowAnomalies/Index');
    }

    public function listing(): JsonResponse
    {
        return response()->json($this->service->listing());
    }

    public function detail(Quest $quest): JsonResponse
    {
        return response()->json($this->service->detail($quest));
    }

    public function outreach(Request $request, Quest $quest): JsonResponse
    {
        $data = $request->validate([
            'anomaly_type' => ['required', 'string', 'max:64'],
            'outreach_summary' => ['required', 'string', 'max:3000'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);

        $this->service->recordOutreach($quest, $request->user(), $data);

        return response()->json(['message' => 'Outreach logged.']);
    }

    public function resolveNote(Request $request, StaffEscrowAnomalyNote $note): JsonResponse
    {
        $this->service->resolveNote($note, $request->user());

        return response()->json(['message' => 'Anomaly note resolved.']);
    }
}
