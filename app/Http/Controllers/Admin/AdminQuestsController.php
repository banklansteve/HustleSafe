<?php

namespace App\Http\Controllers\Admin;

use App\Enums\QuestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportAdminQuestSupportNotesRequest;
use App\Models\AdminQuestFlag;
use App\Models\Quest;
use App\Services\AdminActivityLogger;
use App\Services\Admin\AdminQuestModerationService;
use App\Services\Admin\QuestManagementEngineService;
use App\Support\AdminCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdminQuestsController extends Controller
{
    public function index(Request $request, QuestManagementEngineService $engine): Response
    {
        $payload = $engine->dashboard($request);

        return Inertia::render('Admin/Quests/Index', [
            ...$payload,
        ]);
    }

    public function detail(Quest $quest, QuestManagementEngineService $engine): JsonResponse
    {
        return response()->json($engine->detail($quest));
    }

    public function status(Request $request, Quest $quest, QuestManagementEngineService $engine): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(collect(QuestStatus::cases())->pluck('value')->all())],
            'reason' => ['required', 'string', 'min:3', 'max:120'],
            'note' => ['nullable', 'string', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $engine->changeStatus($quest, $request->user(), $validated, $request);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Quest status updated.', 'quest' => $engine->detail($quest->refresh())]);
        }

        return back()->with('success', __('Quest status updated.'));
    }

    public function adminStatus(Request $request, Quest $quest, AdminQuestModerationService $moderation, QuestManagementEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'admin_status' => ['required', Rule::in($moderation->statusOptions() ? collect($moderation->statusOptions())->pluck('value')->all() : [])],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
            'notification_preview' => ['nullable', 'string', 'max:2000'],
            'referred_to_admin_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $moderation->changeStatus($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Admin moderation status updated.', 'quest' => $engine->detail($quest->refresh())]);
    }

    public function notice(Request $request, Quest $quest, AdminQuestModerationService $moderation, QuestManagementEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['warning', 'informational', 'urgent', 'resolved'])],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'visible_to_users' => ['sometimes', 'boolean'],
            'notify_stakeholders' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNotice($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest notice posted.', 'quest' => $engine->detail($quest->refresh())]);
    }

    public function note(Request $request, Quest $quest, AdminQuestModerationService $moderation, QuestManagementEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer', 'exists:admin_quest_notes,id'],
            'is_pinned' => ['sometimes', 'boolean'],
        ]);

        $moderation->createNote($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Admin note saved.', 'quest' => $engine->detail($quest->refresh())]);
    }

    public function flag(Request $request, Quest $quest, QuestManagementEngineService $engine): RedirectResponse|JsonResponse
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

        $flag = $engine->flag($quest, $request->user(), $validated, $request);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Quest flag created.', 'flag' => $flag]);
        }

        return back()->with('success', __('Quest flag created.'));
    }

    public function resolveFlag(Request $request, Quest $quest, AdminQuestFlag $flag, QuestManagementEngineService $engine): RedirectResponse|JsonResponse
    {
        abort_unless((int) $flag->quest_id === (int) $quest->id, 404);

        $validated = $request->validate([
            'resolution_outcome' => ['required', Rule::in(['actioned_resolved', 'escalated_to_super_admin', 'no_action_required', 'referred_to_another_team'])],
            'resolution_note' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $resolved = $engine->resolveFlag($flag, $request->user(), $validated, $request);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Quest flag resolved.', 'flag' => $resolved]);
        }

        return back()->with('success', __('Quest flag resolved.'));
    }

    public function boost(Request $request, Quest $quest, QuestManagementEngineService $engine): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', Rule::in(['standard', 'premium', 'elite'])],
            'duration_days' => ['required', 'integer', Rule::in([3, 7, 14, 30])],
            'starts_at' => ['nullable', 'date', 'after_or_equal:today'],
            'grant_type' => ['nullable', Rule::in(['paid', 'complimentary'])],
            'paid_upgrade' => ['sometimes', 'boolean'],
            'payment_method' => ['nullable', Rule::in(['wallet', 'payment_link'])],
            'amount_paid_minor' => ['nullable', 'integer', 'min:0'],
            'grant_reason' => ['required', Rule::in(['client_retention', 'platform_promotion', 'compensation_for_issue', 'beta_tester_reward', 'other'])],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $engine->boost($quest, $request->user(), $validated, $request);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Boost package granted.', 'quest' => $engine->detail($quest->refresh())]);
        }

        return back()->with('success', __('Boost package granted.'));
    }

    public function updateQuest(Request $request, Quest $quest, QuestManagementEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'min:5', 'max:180'],
            'description' => ['required', 'string', 'min:20', 'max:12000'],
            'quest_category_id' => ['nullable', 'integer', 'exists:quest_categories,id'],
            'budget_amount' => ['required', 'numeric', 'min:0'],
            'max_offers' => ['nullable', 'integer', 'min:1', 'max:500'],
            'visibility' => ['nullable', Rule::in(['public', 'invite_only', 'private'])],
            'project_type' => ['nullable', Rule::in(['fixed_price', 'hourly'])],
            'start_timing' => ['nullable', Rule::in(['urgent_48h', 'this_week', 'next_two_weeks', 'flexible', 'scheduled', 'window_shopping'])],
            'scheduled_start_date' => ['nullable', 'date'],
            'estimated_completion_days' => ['nullable', 'integer', 'min:1', 'max:730'],
            'due_at' => ['nullable', 'date'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'city' => ['nullable', 'string', 'max:120'],
            'reason' => ['required', 'string', 'min:20', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
            'notification_preview' => ['nullable', 'string', 'max:2000'],
        ]);

        $engine->updateQuest($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest updated and audit trail recorded.', 'quest' => $engine->detail($quest->refresh())]);
    }

    public function destroyQuest(Request $request, Quest $quest, QuestManagementEngineService $engine): JsonResponse
    {
        $validated = $request->validate([
            'confirmation_title' => ['required', 'string', 'max:180'],
            'reason' => ['required', 'string', 'min:30', 'max:2000'],
            'notify_client' => ['sometimes', 'boolean'],
        ]);

        $engine->deleteQuest($quest, $request->user(), $validated, $request);

        return response()->json(['message' => 'Quest deleted and affected users notified.']);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = Quest::query()
            ->with(['client:id,email', 'freelancer:id,email', 'questCategory:id,name']);

        $q = trim((string) $request->input('q', ''));
        if ($q !== '') {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('reference_code', 'like', '%'.$q.'%');
            });
        }

        $status = (string) $request->input('status', '');
        if ($status !== '') {
            $query->where('status', $status);
        }

        $header = [
            'id',
            'reference_code',
            'title',
            'status',
            'escrow_status',
            'client_email',
            'freelancer_email',
            'category',
            'created_at',
        ];

        return AdminCsv::download('quests-'.now()->format('Y-m-d-His').'.csv', $header, function ($out) use ($query): void {
            $query->orderByDesc('id')->chunk(200, function ($quests) use ($out): void {
                foreach ($quests as $quest) {
                    fputcsv($out, [
                        $quest->id,
                        $quest->reference_code,
                        $quest->title,
                        $quest->status instanceof QuestStatus ? $quest->status->value : (string) $quest->status,
                        $quest->escrow_status,
                        $quest->client?->email,
                        $quest->freelancer?->email,
                        $quest->questCategory?->name,
                        $quest->created_at?->toIso8601String(),
                    ]);
                }
            });
        });
    }

    public function import(ImportAdminQuestSupportNotesRequest $request, AdminActivityLogger $logger): RedirectResponse
    {
        $actor = $request->user();
        $path = $request->file('file')->getRealPath();
        if ($path === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => __('Could not read the uploaded file.')]);
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV is empty.')]);
        }

        $norm = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $refIdx = array_search('reference_code', $norm, true);
        $noteIdx = array_search('support_note', $norm, true);
        if ($refIdx === false || $noteIdx === false) {
            fclose($handle);

            return back()->withErrors(['file' => __('CSV must include columns: reference_code, support_note')]);
        }

        $processed = 0;
        $skipped = 0;
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (! isset($row[$refIdx], $row[$noteIdx])) {
                $skipped++;

                continue;
            }

            $code = trim((string) $row[$refIdx]);
            $note = trim((string) $row[$noteIdx]);
            if ($code === '' || $note === '') {
                $skipped++;

                continue;
            }

            $quest = Quest::query()->where('reference_code', $code)->first();
            if ($quest === null) {
                $skipped++;

                continue;
            }

            $logger->log(
                actor: $actor,
                action: 'admin.quest_support_note_import',
                subjectType: Quest::class,
                subjectId: $quest->id,
                properties: [
                    'reference_code' => $code,
                    'support_note' => mb_substr($note, 0, 2000),
                    'csv_row' => $rowNum,
                ],
                request: $request,
            );
            $processed++;
            if ($processed >= 500) {
                break;
            }
        }

        fclose($handle);

        return back()->with('success', __('Logged :n support note(s); skipped :s row(s).', ['n' => $processed, 's' => $skipped]));
    }
}
