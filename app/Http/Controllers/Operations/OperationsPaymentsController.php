<?php

namespace App\Http\Controllers\Operations;

use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Services\Operations\StaffPaymentSupportService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsPaymentsController extends Controller
{
    public function __construct(private readonly StaffPaymentSupportService $service) {}

    public function index(): Response
    {
        $escrowOptions = Quest::query()
            ->whereNotNull('escrow_status')
            ->where('escrow_status', '!=', '')
            ->groupBy('escrow_status')
            ->orderBy('escrow_status')
            ->pluck('escrow_status')
            ->values()
            ->all();

        return Inertia::render('Operations/Payments/Index', [
            'escrow_options' => $escrowOptions,
            'request_types' => config('operations.payment_request_types', []),
        ]);
    }

    public function listing(Request $request): JsonResponse
    {
        $paginator = $this->service->listing($request);

        return response()->json([
            'items' => $paginator->items(),
            'meta' => ['total' => $paginator->total()],
        ]);
    }

    public function detail(Quest $quest): JsonResponse
    {
        return response()->json($this->service->detail($quest));
    }

    public function requestAction(Request $request, Quest $quest): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:hold_payout,release_payout,refund'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
        ]);

        $this->service->requestAction($quest, $request->user(), $data, $request);

        return response()->json(['message' => 'Payment request submitted for Super Admin review.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Quest::query()->with(['client:id,email', 'freelancer:id,email']);

        $escrow = trim((string) $request->input('escrow', ''));
        if ($escrow !== '') {
            $query->where('escrow_status', $escrow);
        }

        $header = [
            'id', 'reference_code', 'title', 'status', 'escrow_status',
            'budget_amount_minor', 'paid_out_minor', 'client_email', 'freelancer_email',
            'escrow_funded_at', 'updated_at',
        ];

        return AdminCsv::download('operations-escrow-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($quests) use ($out): void {
                foreach ($quests as $quest) {
                    fputcsv($out, [
                        $quest->id,
                        $quest->reference_code,
                        $quest->title,
                        $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
                        $quest->escrow_status,
                        $quest->budget_amount_minor,
                        $quest->paid_out_minor,
                        $quest->client?->email,
                        $quest->freelancer?->email,
                        $quest->escrow_funded_at?->toIso8601String(),
                        $quest->updated_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
