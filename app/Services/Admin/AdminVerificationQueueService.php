<?php

namespace App\Services\Admin;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\User;
use App\Models\UserVerification;
use App\Services\Verification\UserVerificationPresentationService;
use App\Support\FormatsHumanDateTime;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;

class AdminVerificationQueueService
{
    public function __construct(
        private readonly UserVerificationPresentationService $presentation,
    ) {}

    /**
     * One canonical row per user + verification type (latest meaningful outcome).
     *
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginatedQueue(Request $request): LengthAwarePaginator
    {
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));
        $page = max(1, (int) $request->input('pending_page', 1));
        $q = trim((string) $request->input('q', ''));
        $type = trim((string) $request->input('type', ''));

        $query = UserVerification::query()
            ->with(['user:id,name,email,created_at,current_verification_level,kyc_tier,verification_tier,avatar_url,email_verified_at', 'reviewer:id,name,email', 'referredToAdmin:id,name,email']);

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('verification_type', 'like', '%'.$q.'%')
                    ->orWhere('category', 'like', '%'.$q.'%')
                    ->orWhere('status', 'like', '%'.$q.'%')
                    ->orWhereHas('user', function (Builder $uq) use ($q): void {
                        $uq->where('email', 'like', '%'.$q.'%')
                            ->orWhere('name', 'like', '%'.$q.'%');
                    });
            });
        }

        if ($type !== '') {
            $query->where(function (Builder $sub) use ($type): void {
                $sub->where('verification_type', $type)
                    ->orWhere('category', $type);
            });
        }

        $rows = $this->canonicalRows($query);

        $sorted = $rows->sort(function (array $a, array $b): int {
            $aNeeds = $a['needs_review'] ? 0 : 1;
            $bNeeds = $b['needs_review'] ? 0 : 1;
            if ($aNeeds !== $bNeeds) {
                return $aNeeds <=> $bNeeds;
            }

            return strcmp((string) ($b['submitted_at'] ?? ''), (string) ($a['submitted_at'] ?? ''));
        })->values();

        $total = $sorted->count();
        $slice = $sorted->slice(($page - 1) * $perPage, $perPage)->values();

        return new Paginator(
            $slice,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'pending_page',
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function accountTimeline(User $user, ?string $typeKey = null): array
    {
        $user->loadMissing(['role:id,slug,name']);
        $typeKey = $typeKey !== null && $typeKey !== '' ? $this->normalizeTypeKey($typeKey) : null;

        $events = [];

        if ($typeKey === null && $user->email_verified_at) {
            $events[] = [
                'id' => 'email-'.$user->id,
                'kind' => 'email',
                'label' => __('Email verification'),
                'status' => 'verified',
                'status_label' => __('Verified'),
                'submitted_at' => $user->email_verified_at->timezone(config('app.timezone'))->toIso8601String(),
                'submitted_at_label' => FormatsHumanDateTime::format($user->email_verified_at, config('app.timezone')),
                'reviewed_at' => $user->email_verified_at->timezone(config('app.timezone'))->toIso8601String(),
                'reviewed_at_label' => FormatsHumanDateTime::format($user->email_verified_at, config('app.timezone')),
                'verification_id' => null,
                'attempt_number' => null,
                'reason' => null,
            ];
        }

        $verifications = UserVerification::query()
            ->where('user_id', $user->id)
            ->with(['reviewer:id,name,email'])
            ->orderBy('submitted_at')
            ->orderBy('id')
            ->get();

        $attemptsByType = [];

        foreach ($verifications as $verification) {
            $verificationTypeKey = $this->typeKey($verification);

            if ($typeKey !== null && $verificationTypeKey !== $typeKey) {
                continue;
            }

            $attemptsByType[$verificationTypeKey] = ($attemptsByType[$verificationTypeKey] ?? 0) + 1;
            $presentation = $this->presentation->forReview($verification, 'admin.user-verifications.document');

            $events[] = [
                'id' => 'verification-'.$verification->id,
                'kind' => 'verification',
                'label' => $presentation['verification_type_label'] ?: $presentation['category_label'],
                'status' => $presentation['status'],
                'status_label' => $presentation['status_label'],
                'submitted_at' => $presentation['submitted_at'],
                'submitted_at_label' => $presentation['submitted_at_label'],
                'reviewed_at' => $presentation['reviewed_at'],
                'reviewed_at_label' => $presentation['reviewed_at_label'],
                'verification_id' => $verification->id,
                'attempt_number' => $attemptsByType[$verificationTypeKey],
                'type_key' => $verificationTypeKey,
                'reason' => $verification->rejection_reason ?: $verification->decision_reason_note,
                'presentation' => $presentation,
            ];
        }

        usort($events, function (array $a, array $b): int {
            return strcmp((string) ($a['submitted_at'] ?? ''), (string) ($b['submitted_at'] ?? ''));
        });

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->slug,
                'role_label' => $user->role?->name,
                'avatar_url' => $user->avatar_url,
                'level' => $user->current_verification_level ?? $user->verification_tier ?? 0,
            ],
            'type_key' => $typeKey,
            'timeline' => $events,
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function canonicalRows(Builder $query): Collection
    {
        /** @var Collection<int, UserVerification> $all */
        $all = (clone $query)
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->get();

        /** @var array<string, UserVerification> $picked */
        $picked = [];

        foreach ($all as $verification) {
            $key = $verification->user_id.'|'.$this->typeKey($verification);

            if (! isset($picked[$key]) || $this->shouldReplace($picked[$key], $verification)) {
                $picked[$key] = $verification;
            }
        }

        return collect($picked)->map(function (UserVerification $verification): array {
            $presentation = $this->presentation->forReview($verification, 'admin.user-verifications.document');
            $status = $verification->status?->value ?? (string) $verification->status;

            return [
                'id' => $verification->id,
                'user_id' => $verification->user_id,
                'type_key' => $this->typeKey($verification),
                'type' => $verification->verification_type ?: $verification->category?->value ?: $verification->category,
                'type_label' => $presentation['verification_type_label'] ?: $presentation['category_label'],
                'status' => $status,
                'status_label' => $presentation['status_label'],
                'submitted_at' => $verification->submitted_at?->toIso8601String(),
                'submitted_at_label' => FormatsHumanDateTime::format($verification->submitted_at, config('app.timezone')),
                'reviewed_at' => $verification->reviewed_at?->toIso8601String(),
                'reviewed_at_label' => FormatsHumanDateTime::format($verification->reviewed_at, config('app.timezone')),
                'attempt_count' => (int) ($verification->attempt_count ?: 1),
                'document_previews' => collect($presentation['documents'] ?? [])->take(4)->values()->all(),
                'reason' => $verification->rejection_reason,
                'concern' => $verification->admin_concern,
                'presentation' => $presentation,
                'needs_review' => $this->needsReview($verification),
                'reviewer' => $verification->reviewer ? [
                    'id' => $verification->reviewer->id,
                    'name' => $verification->reviewer->name,
                    'email' => $verification->reviewer->email,
                ] : null,
                'referred_to_admin' => $verification->referredToAdmin ? [
                    'id' => $verification->referredToAdmin->id,
                    'name' => $verification->referredToAdmin->name,
                    'email' => $verification->referredToAdmin->email,
                ] : null,
                'referred_at' => $verification->referred_at?->toIso8601String(),
                'user' => [
                    'id' => $verification->user?->id,
                    'name' => $verification->user?->name,
                    'email' => $verification->user?->email,
                    'level' => $verification->user?->current_verification_level ?? $verification->user?->verification_tier ?? 0,
                    'account_age_days' => $verification->user?->created_at?->diffInDays(now()),
                    'avatar_url' => $verification->user?->avatar_url,
                ],
            ];
        });
    }

    private function shouldReplace(UserVerification $current, UserVerification $candidate): bool
    {
        $currentPriority = $this->statusPriority($current);
        $candidatePriority = $this->statusPriority($candidate);

        if ($candidatePriority !== $currentPriority) {
            return $candidatePriority > $currentPriority;
        }

        $currentAt = $current->submitted_at ?? $current->created_at;
        $candidateAt = $candidate->submitted_at ?? $candidate->created_at;

        if ($currentAt && $candidateAt) {
            return $candidateAt->gt($currentAt);
        }

        return $candidate->id > $current->id;
    }

    private function statusPriority(UserVerification $verification): int
    {
        $status = $verification->status instanceof UserVerificationStatus
            ? $verification->status
            : UserVerificationStatus::tryFrom((string) $verification->status);

        return match ($status) {
            UserVerificationStatus::Approved, UserVerificationStatus::Verified => 100,
            UserVerificationStatus::Pending, UserVerificationStatus::InReview, UserVerificationStatus::Flagged => 80,
            UserVerificationStatus::Unverified => 60,
            UserVerificationStatus::Rejected => 40,
            UserVerificationStatus::Expired => 20,
            default => 0,
        };
    }

    private function needsReview(UserVerification $verification): bool
    {
        $status = $verification->status instanceof UserVerificationStatus
            ? $verification->status
            : UserVerificationStatus::tryFrom((string) $verification->status);

        return in_array($status, [
            UserVerificationStatus::Pending,
            UserVerificationStatus::InReview,
            UserVerificationStatus::Flagged,
            UserVerificationStatus::Unverified,
            UserVerificationStatus::Rejected,
        ], true);
    }

    private function typeKey(UserVerification $verification): string
    {
        $category = $verification->category instanceof UserVerificationCategory
            ? $verification->category->value
            : (string) ($verification->category ?? '');

        if ($category !== '') {
            return $this->normalizeTypeKey($category);
        }

        $type = trim((string) ($verification->verification_type ?: ''));

        return $this->normalizeTypeKey($type !== '' ? $type : 'unknown');
    }

    private function normalizeTypeKey(string $key): string
    {
        $key = strtolower(trim($key));

        if (in_array($key, [
            'identity',
            'address',
            'identity_address',
            'identity-address',
            'identity_and_address',
            'identity-and-address',
        ], true)) {
            return UserVerificationCategory::IdentityAddress->value;
        }

        return $key;
    }
}
