<?php

namespace App\Services\Onboarding;

use App\Enums\OnboardingQualityReviewStatus;
use App\Enums\UserVerificationStatus;
use App\Models\LoginEvent;
use App\Models\OnboardingQualityReview;
use App\Models\OnboardingQualityReviewAction;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\AdminUserMessageNotification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnboardingQualityControlService
{
    public function __construct(
        private readonly OnboardingQualityEvaluatorService $evaluator,
    ) {}

    public function ensureReviewFor(User $user): ?OnboardingQualityReview
    {
        if (! $this->shouldReview($user)) {
            return null;
        }

        $hours = max(1, (int) config('onboarding_quality.review_window_hours', 48));

        return OnboardingQualityReview::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'user_type' => $this->userType($user),
                'status' => OnboardingQualityReviewStatus::Pending,
                'review_deadline_at' => $this->reviewDeadlineFor($user, $hours),
                'status_changed_at' => now(),
            ],
        );
    }

    private function reviewDeadlineFor(User $user, int $hours): Carbon
    {
        $createdAt = $user->created_at;

        if ($createdAt instanceof \DateTimeInterface) {
            return Carbon::instance($createdAt)->addHours($hours);
        }

        if (is_string($createdAt) && trim($createdAt) !== '') {
            return Carbon::parse($createdAt)->addHours($hours);
        }

        return now()->addHours($hours);
    }

    public function syncEvaluation(User $user): void
    {
        $review = $this->ensureReviewFor($user);
        if ($review === null) {
            return;
        }

        $this->evaluator->applyToReview($review, $user);
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>, filters: array<string, mixed>, nudge_templates: array<string, mixed>}
     */
    public function listing(Request $request): array
    {
        $this->backfillOpenReviews();

        $status = (string) $request->query('status', '');
        $userType = (string) $request->query('user_type', '');
        $search = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'signup_desc');
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));
        $onlyWindow = $request->boolean('within_window', true);

        $query = OnboardingQualityReview::query()
            ->with(['user:id,name,first_name,last_name,email,avatar_url,created_at,profile_completion_percent,role_id', 'user.role:id,slug']);

        if ($onlyWindow) {
            $query->where('review_deadline_at', '>=', now()->subHours((int) config('onboarding_quality.review_window_hours', 48)));
        }

        if ($status !== '' && in_array($status, OnboardingQualityReviewStatus::values(), true)) {
            $query->where('status', $status);
        }

        if ($userType !== '') {
            $query->where('user_type', $userType);
        }

        if ($search !== '') {
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        $query->join('users', 'users.id', '=', 'onboarding_quality_reviews.user_id')
            ->select('onboarding_quality_reviews.*');

        match ($sort) {
            'signup_asc' => $query->orderBy('users.created_at')->orderByDesc('onboarding_quality_reviews.id'),
            'completeness_asc' => $query->orderBy('onboarding_quality_reviews.completeness_score')->orderByDesc('onboarding_quality_reviews.id'),
            'completeness_desc' => $query->orderByDesc('onboarding_quality_reviews.completeness_score')->orderByDesc('onboarding_quality_reviews.id'),
            'deadline_asc' => $query->orderBy('onboarding_quality_reviews.review_deadline_at')->orderByDesc('onboarding_quality_reviews.id'),
            default => $query->orderByDesc('users.created_at')->orderByDesc('onboarding_quality_reviews.id'),
        };

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (OnboardingQualityReview $review) => $this->listRow($review))->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'filters' => [
                'status' => $status,
                'user_type' => $userType,
                'q' => $search,
                'sort' => $sort,
                'within_window' => $onlyWindow,
            ],
            'nudge_templates' => config('onboarding_quality.nudge_templates', []),
            'status_options' => collect(OnboardingQualityReviewStatus::cases())
                ->map(fn (OnboardingQualityReviewStatus $s) => ['value' => $s->value, 'label' => $s->label()])
                ->all(),
        ];
    }

    /**
     * @return array{review: array<string, mixed>, profile: array<string, mixed>, actions: list<array<string, mixed>>}
     */
    public function detail(OnboardingQualityReview $review): array
    {
        $user = User::query()
            ->with([
                'role:id,slug',
                'stateModel:id,name',
                'localGovernmentModel:id,name',
                'userVerifications' => fn ($q) => $q->latest('submitted_at')->limit(20),
                'portfolios' => fn ($q) => $q->latest()->limit(12),
                'portfolios.files',
                'questCategoryPreferences:id,name',
                'freelancerCredentials',
            ])
            ->findOrFail($review->user_id);

        $this->evaluator->applyToReview($review, $user);

        return [
            'review' => $this->listRow($review->refresh()),
            'profile' => $this->profilePayload($user),
            'actions' => $review->actions()
                ->with('admin:id,name,email')
                ->limit(50)
                ->get()
                ->map(fn (OnboardingQualityReviewAction $action) => [
                    'id' => $action->id,
                    'action' => $action->action,
                    'notes' => $action->notes,
                    'payload' => $action->payload,
                    'admin' => $action->admin ? ['id' => $action->admin->id, 'name' => $action->admin->name, 'email' => $action->admin->email] : null,
                    'created_at' => $action->created_at?->toIso8601String(),
                ])
                ->all(),
            'nudge_templates' => config('onboarding_quality.nudge_templates', []),
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function flaggedProfilesListing(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $perPage = max(10, min(100, (int) $request->query('per_page', 25)));

        $query = OnboardingQualityReview::query()
            ->where('monitoring_flagged', true)
            ->with(['user:id,name,first_name,last_name,email,created_at', 'user.role:id,slug']);

        if ($search !== '') {
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $paginator = $query->latest('updated_at')->paginate($perPage)->withQueryString();

        return [
            'items' => collect($paginator->items())->map(fn (OnboardingQualityReview $review) => [
                'id' => $review->id,
                'user' => $this->userBrief($review->user),
                'user_type' => $review->user_type,
                'status' => $review->status?->value,
                'status_label' => $review->status?->label(),
                'monitoring_reason' => $review->monitoring_reason,
                'flags' => $this->evaluator->activeFlagLabels(is_array($review->auto_flags) ? $review->auto_flags : []),
                'completeness_score' => (int) $review->completeness_score,
                'updated_at' => $review->updated_at?->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function applyAction(User $admin, OnboardingQualityReview $review, array $data, bool $superAdminConsole): void
    {
        $action = (string) ($data['action'] ?? '');
        $notes = isset($data['notes']) ? trim((string) $data['notes']) : null;

        $user = User::query()->findOrFail($review->user_id);

        if ($action === 'escalate' && $superAdminConsole) {
            throw ValidationException::withMessages([
                'action' => __('Super admins must resolve escalated cases directly — use Approve, Nudge, Monitoring, or Suspend actions.'),
            ]);
        }

        DB::transaction(function () use ($admin, $review, $user, $action, $data, $notes, $superAdminConsole): void {
            match ($action) {
                'approve' => $this->approve($review, $user),
                'nudge' => $this->nudge($review, $user, $data),
                'request_verification' => $this->requestVerification($review, $user, $data),
                'flag_monitoring' => $this->flagMonitoring($review, $notes),
                'clear_monitoring' => $this->clearMonitoring($review),
                'escalate' => $this->escalate($review, $user, $notes),
                'resolve_escalation' => $this->resolveEscalation($review, $user, $notes),
                'suspend' => $this->suspendPending($review, $user, $notes),
                'lift_suspension' => $this->liftSuspension($review, $user, $notes),
                'override_flags' => $this->overrideFlags($review, $data),
                're_evaluate' => $this->evaluator->applyToReview($review, $user),
                default => throw ValidationException::withMessages(['action' => __('Unknown action.')]),
            };

            $review->forceFill([
                'last_action_admin_id' => $admin->id,
                'status_changed_at' => now(),
            ])->save();

            OnboardingQualityReviewAction::query()->create([
                'onboarding_quality_review_id' => $review->id,
                'admin_id' => $admin->id,
                'action' => $action,
                'notes' => $notes,
                'payload' => collect($data)->except(['action', 'notes'])->all(),
            ]);
        });
    }

    private function approve(OnboardingQualityReview $review, User $user): void
    {
        $review->forceFill(['status' => OnboardingQualityReviewStatus::Approved])->save();
        $user->forceFill([
            'verification_restricted_at' => null,
            'verification_restriction_reason' => null,
            'suspended_at' => null,
        ])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function nudge(OnboardingQualityReview $review, User $user, array $data): void
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));

        if ($subject === '' || $body === '') {
            throw ValidationException::withMessages([
                'body' => __('Nudge subject and message are required.'),
            ]);
        }

        $review->forceFill(['status' => OnboardingQualityReviewStatus::Nudged])->save();
        $user->notify(new AdminUserMessageNotification($subject, $body));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requestVerification(OnboardingQualityReview $review, User $user, array $data): void
    {
        $subject = trim((string) ($data['subject'] ?? 'Additional verification requested'));
        $body = trim((string) ($data['body'] ?? 'Please submit or refresh supporting verification documents so we can confirm profile authenticity.'));

        $review->forceFill(['status' => OnboardingQualityReviewStatus::Nudged])->save();
        $user->notify(new AdminUserMessageNotification($subject, $body));

        UserVerification::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                UserVerificationStatus::Verified->value,
                UserVerificationStatus::Pending->value,
                UserVerificationStatus::InReview->value,
            ])
            ->latest()
            ->limit(1);
    }

    private function flagMonitoring(OnboardingQualityReview $review, ?string $reason): void
    {
        $review->forceFill([
            'monitoring_flagged' => true,
            'monitoring_reason' => $reason ?: 'Flagged for monitoring during onboarding quality review.',
        ])->save();
    }

    private function clearMonitoring(OnboardingQualityReview $review): void
    {
        $review->forceFill([
            'monitoring_flagged' => false,
            'monitoring_reason' => null,
        ])->save();
    }

    private function escalate(OnboardingQualityReview $review, User $user, ?string $notes): void
    {
        $review->forceFill(['status' => OnboardingQualityReviewStatus::Escalated])->save();
        $user->forceFill([
            'verification_restricted_at' => now(),
            'verification_restriction_reason' => $notes ?: 'Escalated from onboarding quality control to Trust & Safety.',
        ])->saveQuietly();
    }

    private function resolveEscalation(OnboardingQualityReview $review, User $user, ?string $notes): void
    {
        if ($review->status !== OnboardingQualityReviewStatus::Escalated) {
            throw ValidationException::withMessages([
                'action' => __('This review is not escalated.'),
            ]);
        }

        $review->forceFill(['status' => OnboardingQualityReviewStatus::Approved])->save();
        $user->forceFill([
            'verification_restricted_at' => null,
            'verification_restriction_reason' => null,
        ])->saveQuietly();
    }

    private function suspendPending(OnboardingQualityReview $review, User $user, ?string $notes): void
    {
        $review->forceFill(['status' => OnboardingQualityReviewStatus::SuspendedPendingReview])->save();
        $user->forceFill([
            'suspended_at' => now(),
            'verification_restriction_reason' => $notes ?: 'Suspended pending onboarding quality review.',
        ])->saveQuietly();
    }

    private function liftSuspension(OnboardingQualityReview $review, User $user, ?string $notes): void
    {
        if ($review->status !== OnboardingQualityReviewStatus::SuspendedPendingReview) {
            throw ValidationException::withMessages([
                'action' => __('This review is not in suspended pending review status.'),
            ]);
        }

        $review->forceFill(['status' => OnboardingQualityReviewStatus::Pending])->save();
        $user->forceFill(['suspended_at' => null])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function overrideFlags(OnboardingQualityReview $review, array $data): void
    {
        $overrides = is_array($data['flag_overrides'] ?? null) ? $data['flag_overrides'] : [];
        $review->forceFill(['manual_flag_overrides' => $overrides])->save();

        $user = User::query()->find($review->user_id);
        if ($user !== null) {
            $this->evaluator->applyToReview($review, $user);
        }
    }

    private function backfillOpenReviews(): void
    {
        $hours = max(1, (int) config('onboarding_quality.review_window_hours', 48));
        $since = now()->subHours($hours);

        User::query()
            ->where('created_at', '>=', $since)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['client', 'freelancer']))
            ->whereDoesntHave('onboardingQualityReview')
            ->orderBy('id')
            ->limit(200)
            ->each(fn (User $user) => $this->ensureReviewFor($user));
    }

    private function shouldReview(User $user): bool
    {
        $slug = $user->role?->slug ?? $user->account_type;

        return in_array($slug, ['client', 'freelancer'], true);
    }

    private function userType(User $user): string
    {
        return $this->isFreelancer($user) ? 'freelancer' : 'client';
    }

    private function isFreelancer(User $user): bool
    {
        $slug = $user->role?->slug ?? $user->account_type;

        return in_array($slug, ['freelancer', 'seller', 'provider'], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function listRow(OnboardingQualityReview $review): array
    {
        $user = $review->user;
        $flags = is_array($review->auto_flags) ? $review->auto_flags : [];

        return [
            'id' => $review->id,
            'user' => $user ? $this->userBrief($user) : null,
            'user_type' => $review->user_type,
            'signup_at' => $user?->created_at?->toIso8601String(),
            'completeness_score' => (int) $review->completeness_score,
            'flags' => $this->evaluator->activeFlagLabels($flags),
            'status' => $review->status?->value,
            'status_label' => $review->status?->label(),
            'monitoring_flagged' => (bool) $review->monitoring_flagged,
            'review_deadline_at' => $review->review_deadline_at?->toIso8601String(),
            'blocks_posting' => $review->blocksPosting(),
            'updated_at' => $review->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profilePayload(User $user): array
    {
        $logins = LoginEvent::query()
            ->where('user_id', $user->id)
            ->latest('logged_in_at')
            ->limit(15)
            ->get(['id', 'ip_address', 'user_agent', 'logged_in_at']);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone' => $user->phone,
                'avatar_url' => $user->avatar_url,
                'headline' => $user->headline,
                'bio' => $user->bio,
                'profession' => $user->profession,
                'years_experience' => $user->years_experience,
                'company_name' => $user->company_name,
                'address_line' => $user->address_line,
                'city' => $user->city,
                'state' => $user->stateModel?->name,
                'local_government' => $user->localGovernmentModel?->name,
                'created_at' => $user->created_at?->toIso8601String(),
                'profile_completion_percent' => (int) $user->profile_completion_percent,
            ],
            'auto_flags' => OnboardingQualityReview::query()->where('user_id', $user->id)->value('auto_flags') ?? [],
            'kyc' => $user->userVerifications->map(fn (UserVerification $v) => [
                'id' => $v->id,
                'category' => $v->category?->value ?? (string) $v->category,
                'verification_type' => $v->verification_type,
                'status' => $v->status?->value ?? (string) $v->status,
                'submitted_at' => $v->submitted_at?->toIso8601String(),
                'reviewed_at' => $v->reviewed_at?->toIso8601String(),
            ])->values()->all(),
            'categories' => $user->questCategoryPreferences->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()->all(),
            'credentials' => $user->freelancerCredentials->map(fn ($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'credential_type' => $c->credential_type,
                'issuing_authority' => $c->issuing_authority,
                'issued_on' => $c->issued_on?->toDateString(),
                'is_verified' => (bool) $c->is_verified,
            ])->values()->all(),
            'portfolios' => $user->portfolios->map(fn ($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'description' => $p->description,
                'status' => $p->status,
                'files' => $p->files->map(fn ($f) => [
                    'id' => $f->id,
                    'url' => $f->url(),
                    'original_name' => $f->original_name,
                ])->values()->all(),
            ])->values()->all(),
            'login_history' => $logins->map(fn (LoginEvent $e) => [
                'id' => $e->id,
                'ip_address' => $e->ip_address,
                'user_agent' => $e->user_agent,
                'logged_in_at' => $e->logged_in_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userBrief(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
        ];
    }
}
