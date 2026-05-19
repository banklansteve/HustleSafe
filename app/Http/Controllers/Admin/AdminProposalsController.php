<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkAdminProposalActionRequest;
use App\Http\Requests\Admin\StoreAdminProposalFlagRequest;
use App\Http\Requests\Admin\StoreAdminProposalNoteRequest;
use App\Http\Requests\Admin\StoreAdminProposalNoticeRequest;
use App\Http\Requests\Admin\UpdateAdminProposalContentRequest;
use App\Http\Requests\Admin\UpdateAdminProposalStatusRequest;
use App\Models\AdminProposalFlag;
use App\Models\QuestOffer;
use App\Services\Admin\AdminProposalModerationService;
use App\Services\Admin\ProposalManagementEngineService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminProposalsController extends Controller
{
    public function index(Request $request, ProposalManagementEngineService $engine): Response
    {
        return Inertia::render('Admin/Proposals/Index', [
            ...$engine->dashboard($request),
        ]);
    }

    public function detail(QuestOffer $proposal, ProposalManagementEngineService $engine): JsonResponse
    {
        return response()->json($engine->detail($proposal));
    }

    public function adminStatus(UpdateAdminProposalStatusRequest $request, QuestOffer $proposal, AdminProposalModerationService $moderation, ProposalManagementEngineService $engine): JsonResponse
    {
        $moderation->changeStatus($proposal, $request->user(), $request->validated(), $request);

        return response()->json(['message' => 'Proposal moderation status updated.', 'proposal' => $engine->detail($proposal->refresh())]);
    }

    public function flag(StoreAdminProposalFlagRequest $request, QuestOffer $proposal, AdminProposalModerationService $moderation): JsonResponse
    {
        $flag = $moderation->createFlag($proposal, $request->user(), $request->validated(), $request);

        return response()->json(['message' => 'Proposal flag created.', 'flag' => $flag]);
    }

    public function resolveFlag(Request $request, QuestOffer $proposal, AdminProposalFlag $flag, AdminProposalModerationService $moderation): JsonResponse
    {
        abort_unless((int) $flag->quest_offer_id === (int) $proposal->id, 404);

        $validated = $request->validate([
            'resolution_outcome' => ['required', Rule::in(['actioned_resolved', 'escalated_to_super_admin', 'no_action_required', 'referred_to_another_team'])],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $resolved = $moderation->resolveFlag($flag, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal flag resolved.', 'flag' => $resolved]);
    }

    public function notice(StoreAdminProposalNoticeRequest $request, QuestOffer $proposal, AdminProposalModerationService $moderation, ProposalManagementEngineService $engine): JsonResponse
    {
        $moderation->createNotice($proposal, $request->user(), $request->validated(), $request);

        return response()->json(['message' => 'Proposal notice posted.', 'proposal' => $engine->detail($proposal->refresh())]);
    }

    public function note(StoreAdminProposalNoteRequest $request, QuestOffer $proposal, AdminProposalModerationService $moderation, ProposalManagementEngineService $engine): JsonResponse
    {
        $moderation->createNote($proposal, $request->user(), $request->validated(), $request);

        return response()->json(['message' => 'Admin note saved.', 'proposal' => $engine->detail($proposal->refresh())]);
    }

    public function updateProposal(UpdateAdminProposalContentRequest $request, QuestOffer $proposal, ProposalManagementEngineService $engine): JsonResponse
    {
        $engine->updateContent($proposal, $request->user(), $request->validated(), $request);

        return response()->json(['message' => 'Proposal content updated and audit trail recorded.', 'proposal' => $engine->detail($proposal->refresh())]);
    }

    public function destroyProposal(Request $request, QuestOffer $proposal, ProposalManagementEngineService $engine): JsonResponse
    {
        abort_unless($request->user()?->role?->slug === 'super_admin', 403);

        $validated = $request->validate([
            'confirmation' => ['required', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:30', 'max:2000'],
        ]);

        $engine->deleteProposal($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal permanently removed and audit trail recorded.']);
    }

    public function bulk(BulkAdminProposalActionRequest $request, AdminProposalModerationService $moderation): JsonResponse
    {
        $data = $request->validated();
        $count = 0;

        QuestOffer::query()
            ->whereIn('id', $data['ids'])
            ->whereNotIn('status', ['accepted'])
            ->chunkById(100, function ($proposals) use (&$count, $data, $request, $moderation): void {
                foreach ($proposals as $proposal) {
                    $payload = [
                        'reason' => $data['reason'],
                        'referred_to_admin_id' => $data['referred_to_admin_id'] ?? null,
                    ];

                    if ($data['action'] === 'flag') {
                        $moderation->createFlag($proposal, $request->user(), [
                            'type' => $data['type'] ?? 'policy_violation',
                            'priority' => $data['priority'] ?? 'medium',
                            'description' => $data['reason'],
                            'visibility_impact' => 'none',
                        ], $request);
                    } elseif ($data['action'] === 'post_notice') {
                        $moderation->createNotice($proposal, $request->user(), [
                            'type' => 'warning',
                            'body' => $data['body'] ?? $data['reason'],
                            'visible_to_freelancer' => true,
                            'visible_to_client' => true,
                        ], $request);
                    } else {
                        $payload['admin_status'] = match ($data['action']) {
                            'restrict' => 'restricted',
                            'suspend' => 'suspended',
                            'refer' => 'referred',
                            default => $data['admin_status'] ?? 'under_review',
                        };
                        $moderation->changeStatus($proposal, $request->user(), $payload, $request);
                    }

                    $count++;
                }
            });

        return response()->json(['message' => "{$count} proposal(s) updated. Accepted contracts were excluded from destructive bulk actions."]);
    }

    public function export(Request $request, ProposalManagementEngineService $engine): StreamedResponse
    {
        $query = $engine->exportQuery($request);
        $header = ['id', 'admin_status', 'operational_status', 'freelancer_email', 'quest', 'proposed_amount_minor', 'created_at'];

        return AdminCsv::download('proposals-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($proposals) use ($out): void {
                foreach ($proposals as $proposal) {
                    fputcsv($out, [
                        $proposal->id,
                        $proposal->admin_status?->value ?? (string) $proposal->admin_status,
                        $proposal->status,
                        $proposal->freelancer?->email,
                        $proposal->quest?->title,
                        $proposal->quoted_amount_minor,
                        $proposal->created_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }
}
