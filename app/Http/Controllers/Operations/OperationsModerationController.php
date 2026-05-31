<?php

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Quest;
use App\Models\QuestFile;
use App\Models\QuestOffer;
use App\Services\Operations\StaffQuestModerationService;
use App\Services\Admin\AdminProposalModerationService;
use App\Services\Admin\AdminQuestModerationService;
use App\Services\Admin\ProposalManagementEngineService;
use App\Services\Admin\QuestManagementEngineService;
use App\Services\ConversationMonitoring\ConversationMonitoringAdminService;
use App\Services\Operations\StaffModerationQueueService;
use App\Support\Operations\StaffCapabilities;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OperationsModerationController extends Controller
{
    public function index(Request $request, StaffModerationQueueService $queues, ConversationMonitoringAdminService $conversationMonitoring): Response
    {
        return Inertia::render('Operations/Moderation/Index', [
            'quest_queues' => $queues->questQueues(),
            'proposal_queues' => $queues->proposalQueues(),
            'options' => $queues->options(),
            'conversation_monitoring_summary' => $conversationMonitoring->summary($request->user()),
            'capabilities' => [
                'quest_admin_statuses' => StaffCapabilities::questAdminStatusValues(),
                'proposal_admin_statuses' => StaffCapabilities::proposalAdminStatusValues(),
            ],
        ]);
    }

    public function questListing(Request $request, StaffModerationQueueService $queues): JsonResponse
    {
        return response()->json($queues->questListing($request));
    }

    public function proposalListing(Request $request, StaffModerationQueueService $queues): JsonResponse
    {
        return response()->json($queues->proposalListing($request));
    }

    public function questDetail(Quest $quest, StaffModerationQueueService $queues): JsonResponse
    {
        return response()->json($queues->questDetail($quest->id));
    }

    public function proposalDetail(QuestOffer $proposal, StaffModerationQueueService $queues): JsonResponse
    {
        return response()->json($queues->proposalDetail($proposal->id));
    }

    public function questAdminStatus(Request $request, Quest $quest, AdminQuestModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'admin_status' => ['required', Rule::in(StaffCapabilities::questAdminStatusValues())],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $moderation->changeStatus($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest admin status updated.', 'quest' => $queues->questDetail($quest->id)]);
    }

    public function questNotice(Request $request, Quest $quest, AdminQuestModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['warning', 'informational', 'urgent', 'resolved'])],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'visible_to_users' => ['sometimes', 'boolean'],
            'notify_stakeholders' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNotice($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest notice posted.', 'quest' => $queues->questDetail($quest->id)]);
    }

    public function questNote(Request $request, Quest $quest, AdminQuestModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:admin_quest_notes,id'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNote($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest note saved.', 'quest' => $queues->questDetail($quest->id)]);
    }

    public function questFlag(Request $request, Quest $quest, QuestManagementEngineService $engine, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['suspicious_content', 'off_platform_solicitation', 'budget_anomaly', 'duplicate_quest', 'fraudulent_posting', 'policy_violation', 'client_complaint', 'needs_featured_review', 'requires_escrow_attention', 'other'])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'assigned_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'assigned_group' => ['nullable', Rule::in(['all_moderation_admins', 'all_finance_admins', 'all_super_admins'])],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'visibility_impact' => ['nullable', Rule::in(['none', 'restrict_new_proposals', 'hide_pending_resolution'])],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $engine->flag($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest flag created.', 'quest' => $queues->questDetail($quest->id)]);
    }

    public function proposalAdminStatus(Request $request, QuestOffer $proposal, AdminProposalModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'admin_status' => ['required', Rule::in(StaffCapabilities::proposalAdminStatusValues())],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_freelancer' => ['sometimes', 'boolean'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $moderation->changeStatus($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal admin status updated.', 'proposal' => $queues->proposalDetail($proposal->id)]);
    }

    public function proposalNotice(Request $request, QuestOffer $proposal, AdminProposalModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['warning', 'informational', 'urgent', 'resolved'])],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'visible_to_users' => ['sometimes', 'boolean'],
            'blocks_acceptance' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNotice($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal notice posted.', 'proposal' => $queues->proposalDetail($proposal->id)]);
    }

    public function proposalNote(Request $request, QuestOffer $proposal, AdminProposalModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:admin_proposal_notes,id'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNote($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal note saved.', 'proposal' => $queues->proposalDetail($proposal->id)]);
    }

    public function proposalFlag(Request $request, QuestOffer $proposal, AdminProposalModerationService $moderation, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([
                'off_platform_contact', 'solicitation', 'lowball_bid', 'copy_paste', 'velocity_spam',
                'coordinated_bidding', 'high_value_low_tier', 'prior_admin_actions', 'policy_violation', 'other',
            ])],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'critical'])],
            'description' => ['required', 'string', 'min:30', 'max:2000'],
            'due_at' => ['nullable', 'date', 'after_or_equal:today'],
            'visibility_impact' => ['nullable', Rule::in(['none', 'restrict_acceptance', 'hide_pending_resolution'])],
            'notify_freelancer' => ['sometimes', 'boolean'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $moderation->createFlag($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal flag created.', 'proposal' => $queues->proposalDetail($proposal->id)]);
    }

    public function questUpdate(Request $request, Quest $quest, StaffQuestModerationService $staffQuest, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:12000'],
            'quest_category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:500'],
            'city' => ['nullable', 'string', 'max:120'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
            'submit_for_approval' => ['sometimes', 'boolean'],
        ]);

        $result = $staffQuest->updateQuest($quest, $request->user(), $validated, $request);

        return response()->json($result);
    }

    public function questRemoveFile(Request $request, Quest $quest, QuestFile $file, StaffQuestModerationService $staffQuest): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        return response()->json($staffQuest->removeFile($quest, $file, $request->user(), $validated, $request));
    }

    public function questContact(Request $request, Quest $quest, StaffQuestModerationService $staffQuest): JsonResponse
    {
        $quest->loadMissing(['client', 'freelancer', 'acceptedOffer.freelancer']);

        $validated = $request->validate([
            'recipient' => ['required', Rule::in(['client', 'freelancer'])],
            'freelancer_id' => ['nullable', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'channel' => ['nullable', Rule::in(['email', 'in_app', 'both'])],
            'open_cs_ticket' => ['sometimes', 'boolean'],
        ]);

        if (($validated['recipient'] ?? '') === 'freelancer') {
            if (empty($validated['freelancer_id'])) {
                throw ValidationException::withMessages([
                    'freelancer_id' => __('Select a freelancer who has submitted a proposal on this quest.'),
                ]);
            }

            $hasProposal = $quest->offers()
                ->where('freelancer_id', (int) $validated['freelancer_id'])
                ->whereNotIn('status', ['withdrawn', 'declined'])
                ->exists();

            if (! $hasProposal) {
                throw ValidationException::withMessages([
                    'freelancer_id' => __('That freelancer does not have an active proposal on this quest.'),
                ]);
            }
        }

        return response()->json($staffQuest->contactStakeholder($quest, $request->user(), $validated, $request));
    }

    public function proposalContact(Request $request, QuestOffer $proposal, StaffQuestModerationService $staffQuest, StaffModerationQueueService $queues): JsonResponse
    {
        $proposal->loadMissing(['freelancer', 'quest.client']);
        $freelancer = $proposal->freelancer;
        if ($freelancer === null) {
            return response()->json(['message' => __('No freelancer is attached to this proposal.')], 422);
        }

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:10', 'max:5000'],
            'channel' => ['nullable', Rule::in(['email', 'in_app', 'both'])],
            'open_cs_ticket' => ['sometimes', 'boolean'],
        ]);

        $staffQuest->contactUser($freelancer, $request->user(), $validated, $request, QuestOffer::class, $proposal->id);

        return response()->json([
            'message' => __('Message sent to freelancer.'),
            'proposal' => $queues->proposalDetail($proposal->id),
        ]);
    }

    public function proposalRemove(Request $request, QuestOffer $proposal, ProposalManagementEngineService $engine, StaffModerationQueueService $queues): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:30', 'max:2000'],
            'confirmation' => ['required', 'string', 'in:REMOVE'],
        ]);

        $engine->deleteProposal($proposal, $request->user(), $validated, $request);

        return response()->json(['message' => 'Proposal removed.', 'removed_id' => $proposal->id]);
    }
}
