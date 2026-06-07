<?php

namespace App\Services\Admin;

use App\Enums\PortfolioStatus;
use App\Enums\QuestStatus;
use App\Enums\ReviewStatus;
use App\Models\ActivityLog;
use App\Models\ModerationCase;
use App\Models\ModerationDecision;
use App\Models\ModerationKeyword;
use App\Models\ModerationNotificationTemplate;
use App\Models\ModerationSetting;
use App\Models\Portfolio;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\Review;
use App\Models\User;
use App\Notifications\AdminUserMessageNotification;
use App\Support\PlainText;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentModerationAdminService
{
    public function summary(): array
    {
        return collect([
            'quest' => 'Quest Moderation',
            'profile_portfolio' => 'Profile & Portfolio',
            'review' => 'Reviews & Ratings',
            'appeals' => 'Appeals',
        ])->map(fn (string $label, string $key) => [
            'key' => $key,
            'label' => $label,
            'count' => $key === 'appeals'
                ? DB::table('moderation_appeals')->where('status', 'open')->count()
                : ModerationCase::query()->where('queue', $key)->whereIn('status', ['open', 'in_review'])->count(),
        ])->values()->all();
    }

    public function queue(Request $request, string $section): LengthAwarePaginator
    {
        $query = ModerationCase::query()
            ->with([
                'subjectUser:id,name,email,created_at',
                'subjectUser.trustMetrics:user_id,freelancer_trust_score,client_trust_score',
                'reporter:id,name,email',
                'triggers',
                'moderatable',
            ])
            ->whereIn('status', ['open', 'in_review']);

        if ($section === 'quests') {
            $query->where('queue', 'quest');
        } elseif ($section === 'profiles') {
            $query->where('queue', 'profile_portfolio');
        } elseif ($section === 'reviews') {
            $query->where('queue', 'review');
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->input('severity'));
        }
        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(fn (Builder $q) => $q->where('title', 'like', '%'.$search.'%')->orWhere('excerpt', 'like', '%'.$search.'%'));
        }

        $sort = $request->input('sort') === 'severity' ? 'severity' : 'entered_queue_at';
        $sort === 'severity' ? $query->orderByRaw("field(severity, 'critical', 'warning', 'info')") : $query->oldest('entered_queue_at');

        return $query->paginate(min(50, max(10, $request->integer('per_page', 20))))
            ->withQueryString()
            ->through(fn (ModerationCase $case) => $this->casePayload($case));
    }

    public function history(Request $request): LengthAwarePaginator
    {
        $query = ModerationDecision::query()->with(['case.moderatable', 'case.subjectUser:id,name,email', 'admin:id,name,email']);

        if ($request->filled('q')) {
            $search = trim((string) $request->input('q'));
            $query->where(function (Builder $q) use ($search): void {
                $q->where('action', 'like', '%'.$search.'%')
                    ->orWhere('reason_code', 'like', '%'.$search.'%')
                    ->orWhereHas('case', fn (Builder $case) => $case->where('title', 'like', '%'.$search.'%'));
            });
        }

        return $query->latest()
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ModerationDecision $decision) => [
                'id' => $decision->id,
                'content' => PlainText::from($decision->case?->title, 180),
                'content_type' => $decision->case?->content_type,
                'decision' => $decision->action,
                'admin' => $decision->admin?->name,
                'time_to_decision' => gmdate('H:i:s', (int) $decision->time_to_decision_seconds),
                'reason' => $decision->reason_code,
                'created_at' => $decision->created_at?->toIso8601String(),
            ]);
    }

    public function settings(): array
    {
        return [
            'keywords' => ModerationKeyword::query()->latest()->get(),
            'settings' => [
                'new_account_review_hours' => ModerationSetting::value('new_account_review_hours', 48),
                'allowed_external_domains' => ModerationSetting::value('allowed_external_domains', []),
                'cloudinary_moderation_enabled' => ModerationSetting::value('cloudinary_moderation_enabled', true),
            ],
            'templates' => ModerationNotificationTemplate::query()->orderBy('label')->get(),
        ];
    }

    public function metrics(): array
    {
        $decisions = ModerationDecision::query()->with('case')->get();

        return [
            'average_time_to_decision' => gmdate('H:i:s', (int) round((float) $decisions->avg('time_to_decision_seconds'))),
            'approval_rate' => $this->rate($decisions->whereIn('action', ['approve', 'approve_warning', 'edit_approve'])->count(), max(1, $decisions->count())),
            'removal_rate' => $this->rate($decisions->whereIn('action', ['remove', 'remove_warn', 'remove_suspend'])->count(), max(1, $decisions->count())),
            'admin_review_counts' => $decisions->groupBy('admin_user_id')->map->count()->values()->all(),
        ];
    }

    public function decide(ModerationCase $case, User $admin, array $data): ModerationDecision
    {
        return DB::transaction(function () use ($case, $admin, $data): ModerationDecision {
            $case->loadMissing('moderatable', 'subjectUser');
            $action = (string) $data['action'];
            $this->applyContentAction($case, $action, $data['edited'] ?? []);

            $seconds = max(0, $case->entered_queue_at?->diffInSeconds(now()) ?? 0);
            $decision = $case->decisions()->create([
                'admin_user_id' => $admin->id,
                'action' => $action,
                'reason_code' => $data['reason_code'],
                'note' => $data['note'] ?? null,
                'edited_snapshot' => $data['edited'] ?? null,
                'time_to_decision_seconds' => $seconds,
            ]);

            $case->forceFill([
                'status' => in_array($action, ['fraud_investigation', 'request_revision'], true) ? $action : 'decided',
                'decision' => $action,
                'decision_reason' => $data['reason_code'],
                'decision_note' => $data['note'] ?? null,
                'decided_at' => now(),
                'assigned_admin_id' => $admin->id,
            ])->save();

            $this->notifySubject($case, $action, $data['reason_code'], $data['note'] ?? null);
            $this->logDecision($case, $admin, $decision);

            return $decision;
        });
    }

    public function casePayload(ModerationCase $case): array
    {
        $case->loadMissing([
            'triggers',
            'subjectUser:id,name,email,created_at',
            'subjectUser.trustMetrics:user_id,freelancer_trust_score,client_trust_score',
            'reporter:id,name,email',
        ]);
        $model = $case->moderatable;

        return [
            'id' => $case->id,
            'uuid' => $case->uuid,
            'title' => PlainText::from($case->title),
            'excerpt' => PlainText::from($case->excerpt, 320),
            'content_type' => $case->content_type,
            'queue' => $case->queue,
            'status' => $case->status,
            'severity' => $case->severity,
            'visibility_state' => $case->visibility_state,
            'source' => $case->source,
            'confidence' => $case->confidence,
            'waiting_for' => $case->entered_queue_at?->diffForHumans(),
            'entered_queue_at' => $case->entered_queue_at?->toIso8601String(),
            'subject' => $case->subjectUser ? [
                'id' => $case->subjectUser->id,
                'name' => $case->subjectUser->name,
                'email' => $case->subjectUser->email,
                'account_age' => $case->subjectUser->created_at?->diffForHumans(),
                'trust_score' => $case->subjectUser->trust_score ?? $case->subjectUser->client_trust_score ?? null,
            ] : null,
            'reporter' => $case->reporter?->only(['id', 'name', 'email']),
            'triggers' => $case->triggers->map(fn ($trigger) => [
                'rule_key' => $trigger->rule_key,
                'rule_type' => $trigger->rule_type,
                'category' => PlainText::from($trigger->category),
                'severity' => $trigger->severity,
                'confidence' => $trigger->confidence,
                'matched_text' => PlainText::from($trigger->matched_text, 240),
                'context' => PlainText::from($trigger->context, 320),
                'meta' => $trigger->meta ?? [],
            ])->values(),
            'snapshot' => $this->plainSnapshot($case->snapshot ?? []),
            'model' => $this->modelSummary($model),
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function plainSnapshot(array $snapshot): array
    {
        $plain = $snapshot;

        foreach (['text', 'title', 'report_reason', 'report_details'] as $key) {
            if (isset($plain[$key]) && is_string($plain[$key])) {
                $plain[$key] = PlainText::from($plain[$key]);
            }
        }

        return $plain;
    }

    private function applyContentAction(ModerationCase $case, string $action, array $edited): void
    {
        $model = $case->moderatable;
        if (! $model instanceof Model) {
            return;
        }

        if ($action === 'edit_approve') {
            $this->applyEdits($model, $edited);
        }

        if (in_array($action, ['remove', 'remove_warn', 'remove_suspend'], true)) {
            if ($model instanceof Quest) {
                $model->forceFill(['status' => QuestStatus::CancelledByAdmin])->save();
            } elseif ($model instanceof QuestOffer) {
                $model->forceFill(['status' => 'removed'])->save();
            } elseif ($model instanceof Portfolio) {
                $model->forceFill(['status' => PortfolioStatus::Removed, 'admin_hidden' => true])->save();
            } elseif ($model instanceof Review) {
                $model->forceFill(['status' => ReviewStatus::Removed])->save();
            } elseif ($model instanceof User) {
                $model->forceFill(['under_review_at' => now()])->save();
            }
        }

        if ($action === 'request_revision') {
            if ($model instanceof Portfolio) {
                $model->forceFill(['status' => PortfolioStatus::RevisionRequested, 'admin_hidden' => true])->save();
            } elseif ($model instanceof Review) {
                $model->forceFill(['status' => ReviewStatus::RevisionRequested])->save();
            }
        }

        if ($action === 'remove_suspend' && $case->subjectUser) {
            $case->subjectUser->forceFill(['suspended_at' => now()])->save();
        }

        if ($action === 'approve' || $action === 'approve_warning' || $action === 'edit_approve') {
            if ($model instanceof Portfolio) {
                $model->forceFill(['status' => PortfolioStatus::Published, 'admin_hidden' => false, 'published_at' => $model->published_at ?? now()])->save();
            } elseif ($model instanceof Review) {
                $model->forceFill(['status' => ReviewStatus::Published])->save();
            }
        }
    }

    private function applyEdits(Model $model, array $edited): void
    {
        if ($model instanceof Quest || $model instanceof Portfolio) {
            $model->fill(array_filter([
                'title' => $edited['title'] ?? null,
                'description' => $edited['description'] ?? null,
            ], fn ($value) => $value !== null));
            $model->save();
        } elseif ($model instanceof Review) {
            $model->fill(array_filter([
                'title' => $edited['title'] ?? null,
                'comment' => $edited['comment'] ?? null,
            ], fn ($value) => $value !== null));
            $model->save();
        } elseif ($model instanceof User && array_key_exists('bio', $edited)) {
            $model->forceFill(['bio' => $edited['bio']])->save();
        }
    }

    private function notifySubject(ModerationCase $case, string $action, string $reason, ?string $note): void
    {
        $user = $case->subjectUser;
        if (! $user) {
            return;
        }

        $templateKey = match ($action) {
            'approve_warning', 'edit_approve' => 'approved_with_warning',
            'request_revision' => 'revision_requested',
            default => in_array($action, ['remove', 'remove_warn', 'remove_suspend'], true) ? 'removed' : null,
        };

        if ($templateKey === null) {
            return;
        }

        $template = ModerationNotificationTemplate::query()->where('key', $templateKey)->where('is_active', true)->first();
        $subject = $template?->subject ?? 'Content moderation update';
        $body = str_replace('{{reason}}', Str::headline($reason).($note ? ': '.$note : ''), $template?->body ?? 'Your content moderation status changed.');
        $user->notify(new AdminUserMessageNotification($subject, $body));
    }

    private function logDecision(ModerationCase $case, User $admin, ModerationDecision $decision): void
    {
        ActivityLog::query()->create([
            'subject_user_id' => $case->subject_user_id,
            'actor_id' => $admin->id,
            'type' => 'moderation.'.$decision->action,
            'title' => 'Moderation decision recorded',
            'body' => "{$decision->action} on {$case->content_type}: {$case->title}",
            'meta' => ['moderation_case_id' => $case->id, 'decision_id' => $decision->id],
            'created_at' => now(),
        ]);
    }

    private function modelSummary(?Model $model): array
    {
        if (! $model) {
            return [];
        }

        return [
            'class' => $model::class,
            'id' => $model->getKey(),
            'fields' => collect($model->getAttributes())
                ->except(['password', 'remember_token'])
                ->take(24)
                ->all(),
        ];
    }

    private function rate(int $part, int $total): string
    {
        return round(($part / max(1, $total)) * 100, 1).'%';
    }
}
