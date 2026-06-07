<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\User;
use App\Models\UserVerification;
use Illuminate\Support\Collection;

final class UserVerificationCatalogService
{
    public function __construct(
        private readonly VerificationEngineService $engine,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        $user->loadMissing(['role:id,slug', 'stateModel:id,name']);

        if ($user->verification_level_override === null) {
            $resolved = $this->engine->resolvedVerificationLevel($user);
            if ($resolved !== $this->engine->storedLevel($user)) {
                $this->engine->recalculate($user, null, 'Verification catalog sync.');
                $user->refresh();
            }
        }

        $isFreelancer = $this->isFreelancer($user);
        $verifications = $user->userVerifications()->latest('submitted_at')->get();
        $trust = $this->engine->trustSummaryFor($user, $isFreelancer);
        $nextStep = $this->buildNextStep($user, $isFreelancer, $verifications, $trust);

        return [
            'is_freelancer' => $isFreelancer,
            'trust' => array_merge($trust, [
                'next_hint' => $this->resolveNextHint($user, $nextStep, $trust),
            ]),
            'next_step' => $nextStep,
            'prefilled_address' => $this->prefilledAddress($user),
            'submissions' => $verifications->map(fn (UserVerification $v) => [
                'id' => $v->id,
                'category' => $v->category->value,
                'category_label' => $this->slotTitle($v->category->value),
                'status' => $v->status->value,
                'status_label' => $this->statusLabel($v->status),
                'submitted_at' => $v->submitted_at?->timezone('Africa/Lagos')->toIso8601String(),
                'submitted_at_label' => \App\Support\FormatsHumanDateTime::format($v->submitted_at, 'Africa/Lagos'),
                'rejection_reason' => $v->rejection_reason,
            ])->values()->all(),
            'feedback' => $this->feedbackForUser($user, request()->integer('verification_id') ?: null),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function feedbackForUser(User $user, ?int $verificationId = null): ?array
    {
        if (! $verificationId) {
            return null;
        }

        $verification = UserVerification::query()
            ->where('user_id', $user->id)
            ->whereKey($verificationId)
            ->whereNotNull('reviewed_at')
            ->first();
        if ($verification === null) {
            return null;
        }

        $reasons = app(VerificationDecisionReasonService::class);
        $action = match ($verification->status) {
            UserVerificationStatus::Verified, UserVerificationStatus::Approved => 'approve',
            UserVerificationStatus::Rejected => 'reject',
            UserVerificationStatus::Unverified => 'request_corrections',
            default => null,
        };

        if ($action === null) {
            return null;
        }

        $reasonLabel = $reasons->label($verification->decision_reason_code);

        return [
            'verification_id' => $verification->id,
            'action' => $action,
            'action_label' => match ($action) {
                'approve' => __('Approved'),
                'reject' => __('Not approved'),
                'request_corrections' => __('Corrections requested'),
                default => __('Reviewed'),
            },
            'category_label' => $this->slotTitle($verification->category->value),
            'reason_code' => $verification->decision_reason_code,
            'reason_label' => $reasonLabel,
            'reason_note' => $verification->decision_reason_note,
            'reason_display' => $verification->rejection_reason ?: trim(($reasonLabel ?? '').($verification->decision_reason_note ? '. '.$verification->decision_reason_note : '')),
            'reviewed_at_label' => \App\Support\FormatsHumanDateTime::format($verification->reviewed_at, 'Africa/Lagos'),
        ];
    }

    public function isFreelancer(User $user): bool
    {
        $slug = $user->role?->slug ?? $user->account_type;

        return in_array($slug, ['freelancer', 'seller', 'provider'], true);
    }

    /**
     * @param  array<string, mixed>  $trust
     * @return array<string, mixed>
     */
    private function buildNextStep(User $user, bool $isFreelancer, Collection $verifications, array $trust): array
    {
        $completed = $this->engine->completedVerificationTypes($user);
        $currentLevel = (int) $trust['current_level'];

        if (! $user->hasVerifiedEmail()) {
            return $this->withProgressNotice(array_merge(
                $this->stageStep($user, 1, [
                    'type' => 'email',
                    'key' => 'email',
                    'status' => 'available',
                    'title' => __('Verify your email'),
                    'message' => __('Check your inbox for the verification link we sent when you signed up. If you cannot find it, request a new link below.'),
                    'info_bar' => __('Verify your email to reach :level.', ['level' => $this->engine->levelLabel(1, $user)]),
                    'resend_route' => 'verification.send',
                ], $currentLevel),
                $trust,
            ));
        }

        if ($isFreelancer) {
            return $this->buildFreelancerNextStep($user, $verifications, $trust, $completed, $currentLevel);
        }

        return $this->buildClientNextStep($user, $verifications, $trust, $completed, $currentLevel);
    }

    /**
     * @param  array<string, mixed>  $trust
     * @param  list<string>  $completed
     * @return array<string, mixed>
     */
    private function buildClientNextStep(User $user, Collection $verifications, array $trust, array $completed, int $currentLevel): array
    {
        $ageWait = $this->engine->levelAdvanceAgeWaitContext($user);
        if ($ageWait !== null) {
            return $this->withProgressNotice(
                $this->buildAccountAgeWaitStep($user, $currentLevel, $ageWait),
                $trust,
                true,
            );
        }

        $ladder = [
            ['key' => 'identity_address', 'requirement' => 'identity_address'],
            ['key' => 'nin', 'requirement' => 'nin'],
            ['key' => 'bvn', 'requirement' => 'bvn'],
        ];

        foreach ($ladder as $step) {
            if (in_array($step['requirement'], $completed, true)) {
                continue;
            }

            $slot = $this->slotFor($user, $step['key'], $verifications, false);
            if ($slot['status'] === 'hidden') {
                continue;
            }

            $meta = $this->ladderStepMeta($user, $step['requirement'], $trust, false);
            if (! $this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                continue;
            }

            return $this->withProgressNotice(
                $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                $trust,
            );
        }

        return $this->completeStep($user, $currentLevel, false);
    }

    /**
     * @param  array<string, mixed>  $trust
     * @param  list<string>  $completed
     * @return array<string, mixed>
     */
    private function buildFreelancerNextStep(User $user, Collection $verifications, array $trust, array $completed, int $currentLevel): array
    {
        $ageWait = $this->engine->levelAdvanceAgeWaitContext($user);
        if ($ageWait !== null) {
            return $this->withProgressNotice(
                $this->buildAccountAgeWaitStep($user, $currentLevel, $ageWait),
                $trust,
                true,
            );
        }

        if (! in_array('identity_address', $completed, true)) {
            $slot = $this->slotFor($user, 'identity_address', $verifications, true);
            if ($slot['status'] !== 'hidden') {
                $meta = $this->ladderStepMeta($user, 'identity_address', $trust, true);
                if ($this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                    return $this->withProgressNotice(
                        $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                        $trust,
                    );
                }
            }
        }

        if (! in_array('nin', $completed, true)) {
            $slot = $this->slotFor($user, 'nin', $verifications, true);
            if ($slot['status'] !== 'hidden') {
                $meta = $this->ladderStepMeta($user, 'nin', $trust, true);
                if ($this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                    return $this->withProgressNotice(
                        $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                        $trust,
                    );
                }
            }
        }

        if (! in_array('bvn', $completed, true)) {
            $slot = $this->slotFor($user, 'bvn', $verifications, true);
            if ($slot['status'] !== 'hidden') {
                $meta = $this->ladderStepMeta($user, 'bvn', $trust, true);
                if ($this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                    return $this->withProgressNotice(
                        $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                        $trust,
                    );
                }
            }
        }

        if (! in_array('cac', $completed, true) && ! in_array('tin', $completed, true)) {
            $slot = $this->slotFor($user, 'cac_tin', $verifications, true);
            if ($slot['status'] !== 'hidden') {
                $targetLevel = $this->engine->targetLevelForRequirement($user, 'cac')
                    ?? $this->engine->targetLevelForRequirement($user, 'tin')
                    ?? (int) ($trust['next_level'] ?? $this->engine->maxLevelFor($user));
                $meta = $this->ladderStepMeta($user, 'cac', $trust, true, $targetLevel);
                if ($this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                    return $this->withProgressNotice(
                        $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                        $trust,
                    );
                }
            }
        }

        $liveApproved = $this->hasApproved($verifications, \App\Enums\UserVerificationCategory::LivePresence);

        if (! $liveApproved) {
            $slot = $this->slotFor($user, 'live_presence', $verifications, true);
            if ($slot['status'] !== 'hidden') {
                $targetLevel = $this->engine->targetLevelForRequirement($user, 'live_presence')
                    ?? $this->engine->maxLevelFor($user);
                $meta = $this->ladderStepMeta($user, 'live_presence', $trust, true, $targetLevel);
                if ($this->shouldOfferLadderStep($user, $meta['target_level'], $currentLevel)) {
                    if ($slot['status'] === 'locked') {
                        return $this->withProgressNotice(
                            $this->stageStep($user, $meta['target_level'], [
                                'type' => 'verification_form',
                                'kind' => 'verification_ladder',
                                'key' => 'live_presence',
                                'status' => 'locked',
                                'status_label' => $slot['status_label'] ?? __('Requirements not met'),
                                'title' => $meta['content']['title'] ?? $slot['title'],
                                'message' => $slot['description'] ?? ($meta['content']['message'] ?? ''),
                                'info_bar' => $meta['info'],
                                'slot' => $slot,
                            ], $currentLevel),
                            $trust,
                        );
                    }

                    return $this->withProgressNotice(
                        $this->stepFromSlot($slot, $currentLevel, $meta['target_level'], $meta['target_label'], $meta['info'], 'verification_ladder', $meta['content']),
                        $trust,
                    );
                }
            }
        }

        return $this->completeStep($user, $currentLevel, true);
    }

    /**
     * @param  array{target_level: int, target_level_label: string, required_days: int, days_remaining: int, completed_checks?: list<string>}  $wait
     * @return array<string, mixed>
     */
    private function buildAccountAgeWaitStep(User $user, int $currentLevel, array $wait): array
    {
        $targetLevel = (int) $wait['target_level'];
        $requiredDays = (int) $wait['required_days'];
        $daysRemaining = (int) $wait['days_remaining'];
        $completedChecks = array_values(array_filter($wait['completed_checks'] ?? []));
        $content = $this->engine->stageContentFor($user, $targetLevel);
        $checksLine = $completedChecks !== []
            ? implode(', ', $completedChecks)
            : null;

        return $this->stageStep($user, $targetLevel, [
            'type' => 'account_age',
            'key' => 'account_age',
            'status' => $daysRemaining > 0 ? 'waiting' : 'available',
            'title' => __('Waiting for :level', ['level' => $wait['target_level_label']]),
            'message' => $daysRemaining > 0
                ? ($checksLine
                    ? __(':checks. Your account still needs :days more days on HustleSafe before you move to :level.', [
                        'checks' => $checksLine,
                        'days' => $daysRemaining,
                        'level' => $wait['target_level_label'],
                    ])
                    : __('Your account needs :days more days on HustleSafe before you move to :level.', [
                        'days' => $daysRemaining,
                        'level' => $wait['target_level_label'],
                    ]))
                : __('Your account age requirement is satisfied. :level should apply automatically after our systems refresh.', [
                    'level' => $wait['target_level_label'],
                ]),
            'info_bar' => $content['info_bar'] ?? __('Complete the checks for :level first, then wait :required days on HustleSafe to advance.', [
                'level' => $wait['target_level_label'],
                'required' => $requiredDays,
            ]),
            'days_remaining' => $daysRemaining,
            'required_account_age_days' => $requiredDays,
            'completed_checks' => $completedChecks,
        ], $currentLevel);
    }

    private function shouldOfferLadderStep(User $user, int $targetLevel, int $currentLevel): bool
    {
        if ($user->verification_level_override !== null) {
            return true;
        }

        return $targetLevel <= ($currentLevel + 1);
    }

    /**
     * @param  array<string, mixed>  $trust
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withProgressNotice(array $payload, array $trust, bool $waitingForAge = false): array
    {
        $payload['progress_notice'] = $waitingForAge
            ? __('Waiting for account age before the next level')
            : __('Move to the next verification level');

        if (! isset($payload['target_level_limit_formatted']) && isset($trust['next_level_limit_formatted'])) {
            $payload['target_level_limit_formatted'] = $trust['next_level_limit_formatted'];
            $payload['target_level_limit_minor'] = $trust['next_level_limit_minor'];
        }

        return $payload;
    }

    /**
     * @param  array{title?: string, message?: string, info_bar?: string}  $content
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function stageStep(User $user, int $targetLevel, array $extra, int $currentLevel): array
    {
        return array_merge([
            'current_level' => $currentLevel,
            'target_level' => $targetLevel,
            'target_level_label' => $this->engine->levelLabel($targetLevel, $user),
        ], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function completeStep(User $user, int $currentLevel, bool $isFreelancer): array
    {
        $displayLevel = $this->engine->resolvedVerificationLevel($user);

        return [
            'type' => 'complete',
            'key' => 'complete',
            'status' => 'complete',
            'current_level' => $displayLevel,
            'target_level' => $displayLevel,
            'target_level_label' => $this->engine->levelLabel($displayLevel, $user),
            'title' => __('You are fully verified'),
            'message' => $isFreelancer
                ? __('You have completed all required verification steps. Your proposal limit reflects your current level.')
                : __('You have completed all required verification steps for your client account.'),
            'info_bar' => __('You are at :level — the highest verification level for your account type.', [
                'level' => $this->engine->levelLabel($displayLevel, $user),
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $trust
     */
    private function unlockHint(User $user, int $targetLevel, array $trust): string
    {
        $limit = $trust['next_level_limit_formatted'] ?? $this->engine->formatMoneyMinor((int) ($trust['next_level_limit_minor'] ?? 0));

        return __('Complete this step to unlock :level and raise your quest posting limit to :limit.', [
            'level' => $this->engine->levelLabel($targetLevel, $user),
            'limit' => $limit,
        ]);
    }

    /**
     * @param  array<string, mixed>  $trust
     */
    private function freelancerUnlockHint(User $user, int $targetLevel, array $trust, ?string $doc = null): string
    {
        $limit = $trust['next_level_limit_formatted'] ?? $this->engine->formatMoneyMinor($this->engine->limitAtLevel($user, $targetLevel));

        return match ($targetLevel) {
            3 => __('Submit your NIN to unlock L3 and propose on quests up to :limit.', ['limit' => $limit]),
            4 => __('Submit your BVN to unlock L4 and propose on quests up to :limit.', ['limit' => $limit]),
            5 => __('Complete CAC or TIN verification to unlock L5 and propose on quests up to :limit.', ['limit' => $limit]),
            6 => __('Upload a selfie + ID (with :days days account age) to unlock L6 and propose on high-value quests up to :limit.', [
                'days' => $this->engine->accountAgeRequirementDaysForLevel($user, 6) ?? 0,
                'limit' => $limit,
            ]),
            default => __('Complete this step to unlock :level and propose on quests up to :limit.', [
                'level' => $this->engine->levelLabel($targetLevel, $user),
                'limit' => $limit,
            ]),
        };
    }

    /**
     * @param  array<string, mixed>  $slot
     * @param  array{title?: string, message?: string, info_bar?: string}  $content
     * @return array<string, mixed>
     */
    private function stepFromSlot(array $slot, int $currentLevel, int $targetLevel, string $targetLabel, string $infoBar, string $kind = 'trust_ladder', array $content = []): array
    {
        return [
            'type' => $slot['status'] === 'pending' ? 'pending' : 'verification_form',
            'kind' => $kind,
            'key' => $slot['key'],
            'status' => $slot['status'],
            'status_label' => $slot['status_label'] ?? null,
            'current_level' => $currentLevel,
            'target_level' => $targetLevel,
            'target_level_label' => $targetLabel,
            'title' => $content['title'] ?? $slot['title'] ?? '',
            'message' => $content['message'] ?? $slot['description'] ?? '',
            'info_bar' => $content['info_bar'] ?? $infoBar,
            'slot' => $slot,
        ];
    }

    /**
     * @param  array<string, mixed>  $trust
     * @return array{target_level: int, target_label: string, content: array<string, string>, info: string}
     */
    private function ladderStepMeta(User $user, string $requirement, array $trust, bool $isFreelancer, ?int $targetLevel = null): array
    {
        $targetLevel ??= $this->engine->targetLevelForRequirement($user, $requirement)
            ?? (int) ($trust['next_level'] ?? 1);
        $content = $this->engine->stageContentFor($user, $targetLevel);
        $info = $content['info_bar'] ?? ($isFreelancer
            ? $this->freelancerUnlockHint($user, $targetLevel, $trust)
            : $this->unlockHint($user, $targetLevel, $trust));

        return [
            'target_level' => $targetLevel,
            'target_label' => $this->engine->levelLabel($targetLevel, $user),
            'content' => $content,
            'info' => $info,
        ];
    }

    /**
     * @param  array<string, mixed>  $nextStep
     * @param  array<string, mixed>  $trust
     */
    private function resolveNextHint(User $user, array $nextStep, array $trust): string
    {
        if (($nextStep['type'] ?? '') === 'account_age') {
            if (! empty($nextStep['message'])) {
                return (string) $nextStep['message'];
            }
        }

        $nextLevel = isset($trust['next_level']) ? (int) $trust['next_level'] : null;
        $stepLevel = isset($nextStep['target_level']) ? (int) $nextStep['target_level'] : null;

        if ($nextLevel !== null && $nextLevel > 0 && ($stepLevel === null || $stepLevel !== $nextLevel)) {
            $aligned = $this->engine->stageContentFor($user, $nextLevel);
            if (! empty($aligned['info_bar'])) {
                return (string) $aligned['info_bar'];
            }
        }

        if (! empty($nextStep['info_bar'])) {
            return (string) $nextStep['info_bar'];
        }

        return $this->defaultNextHint($user, $trust);
    }

    /**
     * @param  array<string, mixed>  $trust
     */
    private function defaultNextHint(User $user, array $trust): string
    {
        if ($trust['next_level_label'] === null) {
            return __('You have reached the highest verification level for your account.');
        }

        $missing = $this->engine->missingForNextLevelPublic($user);

        return $missing !== []
            ? __('Complete :items to reach :level.', ['items' => implode(', ', $missing), 'level' => $trust['next_level_label']])
            : __('Continue verification to reach :level.', ['level' => $trust['next_level_label']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function slotFor(User $user, string $key, Collection $verifications, bool $isFreelancer): array
    {
        return match ($key) {
            'nin' => $this->numberSlot('nin', UserVerificationCategory::Nin, $verifications, __('NIN'), __('Enter your 11-digit National Identification Number. You can only submit this once — it does not change after approval.')),
            'bvn' => $this->numberSlot('bvn', UserVerificationCategory::Bvn, $verifications, __('BVN'), __('Enter your 11-digit Bank Verification Number. You can only submit this once — it does not change after approval.')),
            'identity_address' => $this->identityAddressSlot($verifications),
            'cac_tin' => $this->cacTinSlot($verifications),
            'professional_certificate' => $this->professionalSlot($verifications),
            'live_presence' => $this->livePresenceSlot($user, $verifications, $isFreelancer),
            default => ['key' => $key, 'status' => 'hidden'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function numberSlot(string $key, UserVerificationCategory $category, Collection $verifications, string $title, string $description): array
    {
        if ($this->hasApproved($verifications, $category)) {
            return ['key' => $key, 'status' => 'hidden'];
        }

        $pending = $this->latestPending($verifications, $category);

        return [
            'key' => $key,
            'title' => $title,
            'description' => $description,
            'status' => $pending ? 'pending' : 'available',
            'status_label' => $pending ? $this->statusLabel($pending->status) : null,
            'pending_submission_id' => $pending?->id,
            'rejection_reason' => $this->latestRejectedReason($verifications, $category),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identityAddressSlot(Collection $verifications): array
    {
        $category = UserVerificationCategory::IdentityAddress;

        if ($this->hasApproved($verifications, $category)) {
            return ['key' => 'identity_address', 'status' => 'hidden'];
        }

        $pending = $this->latestPending($verifications, $category);

        return [
            'key' => 'identity_address',
            'title' => __('Identity & address verification'),
            'description' => __('Submit one government photo ID and proof of address to unlock the next verification level.'),
            'status' => $pending ? 'pending' : 'available',
            'status_label' => $pending ? $this->statusLabel($pending->status) : null,
            'pending_submission_id' => $pending?->id,
            'rejection_reason' => $this->latestRejectedReason($verifications, $category),
            'notice' => __('Only one photo ID is required. The name on your ID must match your HustleSafe account name.'),
            'id_type_options' => [
                ['value' => 'passport', 'label' => __('International passport')],
                ['value' => 'national_id', 'label' => __('National ID card')],
                ['value' => 'voters_card', 'label' => __('Voter\'s registration card')],
                ['value' => 'drivers_licence', 'label' => __('National driver\'s licence')],
            ],
            'identity_requirements' => [
                __('International passport — number and upload'),
                __('National ID card — number and upload'),
                __('Voter\'s registration card — number and upload'),
                __('National driver\'s licence — number and upload'),
            ],
            'address_hint' => __('Upload at least one proof of address from the last 3 months: PHCN bill, water bill, rent receipt or tenancy agreement, or bank statement showing your name and address. Names must match your account.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function cacTinSlot(Collection $verifications): array
    {
        if ($this->hasApproved($verifications, UserVerificationCategory::Cac)
            || $this->hasApproved($verifications, UserVerificationCategory::Tin)) {
            return ['key' => 'cac_tin', 'status' => 'hidden'];
        }

        $pendingCac = $this->latestPending($verifications, UserVerificationCategory::Cac);
        $pendingTin = $this->latestPending($verifications, UserVerificationCategory::Tin);
        $pending = $pendingCac ?? $pendingTin;

        return [
            'key' => 'cac_tin',
            'title' => __('CAC / TIN verification'),
            'description' => __('Submit your RC number (CAC) or TIN. You only need one of these for business verification. Certificate upload is optional.'),
            'status' => $pending ? 'pending' : 'available',
            'status_label' => $pending ? $this->statusLabel($pending->status) : null,
            'pending_submission_id' => $pending?->id,
            'rejection_reason' => $this->latestRejectedReason($verifications, UserVerificationCategory::Cac)
                ?? $this->latestRejectedReason($verifications, UserVerificationCategory::Tin),
            'notice' => __('You only need to complete either CAC or TIN — not both.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function professionalSlot(Collection $verifications): array
    {
        return ['key' => 'professional_certificate', 'status' => 'hidden'];
    }

    /**
     * @return array<string, mixed>
     */
    private function livePresenceSlot(User $user, Collection $verifications, bool $isFreelancer): array
    {
        if (! $isFreelancer) {
            return ['key' => 'live_presence', 'status' => 'hidden'];
        }

        $businessOk = $this->hasApproved($verifications, UserVerificationCategory::Cac)
            || $this->hasApproved($verifications, UserVerificationCategory::Tin);

        if ($this->hasApproved($verifications, UserVerificationCategory::LivePresence)) {
            return ['key' => 'live_presence', 'status' => 'hidden'];
        }

        if (! $businessOk) {
            return [
                'key' => 'live_presence',
                'title' => __('Selfie + ID (L6)'),
                'description' => __('Complete CAC or TIN verification first, then return here for your selfie + ID check.'),
                'status' => 'locked',
                'status_label' => __('Complete CAC or TIN first'),
            ];
        }

        $pending = $this->latestPending($verifications, UserVerificationCategory::LivePresence);

        return [
            'key' => 'live_presence',
            'title' => __('Selfie + ID (high-value quest unlock)'),
            'description' => __('Take a selfie holding a government-approved ID close to your face. Neck upward only, well lit, plain light background. The ID photo must be readable beside your face.'),
            'status' => $pending ? 'pending' : 'available',
            'status_label' => $pending ? $this->statusLabel($pending->status) : null,
            'pending_submission_id' => $pending?->id,
            'instructions' => [
                __('Show only your face from the neck up.'),
                __('Hold your passport, driver\'s licence, or voter\'s card close to your cheek.'),
                __('Use a well-lit room with a plain light background.'),
                __('One clear photo (JPEG/PNG/WebP, max 15 MB).'),
            ],
        ];
    }

    private function hasApproved(Collection $verifications, UserVerificationCategory $category): bool
    {
        return $verifications->contains(fn (UserVerification $v) => $v->category === $category && $v->status->isVerified());
    }

    private function latestPending(Collection $verifications, UserVerificationCategory $category): ?UserVerification
    {
        return $verifications->first(fn (UserVerification $v) => $v->category === $category
            && in_array($v->status, [UserVerificationStatus::Pending, UserVerificationStatus::InReview], true));
    }

    private function latestRejectedReason(Collection $verifications, UserVerificationCategory $category): ?string
    {
        $latest = $verifications->first(fn (UserVerification $v) => $v->category === $category
            && in_array($v->status, [UserVerificationStatus::Rejected, UserVerificationStatus::Unverified], true));

        return $latest?->rejection_reason;
    }

    /**
     * @return array{line: string, city: string|null, state: string|null, formatted: string}
     */
    private function prefilledAddress(User $user): array
    {
        $parts = array_filter([
            $user->address_line,
            $user->city,
            $user->stateModel?->name,
        ]);

        return [
            'line' => $user->address_line ?? '',
            'city' => $user->city,
            'state' => $user->stateModel?->name,
            'formatted' => implode(', ', $parts),
        ];
    }

    private function slotTitle(string $category): string
    {
        return match ($category) {
            'nin' => __('NIN'),
            'bvn' => __('BVN'),
            'identity_address' => __('Identity & address'),
            'cac' => __('CAC'),
            'tin' => __('TIN'),
            'professional_certificate' => __('Professional certificate'),
            'live_presence' => __('Selfie + ID'),
            default => str_replace('_', ' ', $category),
        };
    }

    private function statusLabel(UserVerificationStatus $status): string
    {
        return match ($status) {
            UserVerificationStatus::Pending => __('Pending review'),
            UserVerificationStatus::InReview => __('In review'),
            UserVerificationStatus::Approved, UserVerificationStatus::Verified => __('Approved'),
            UserVerificationStatus::Rejected => __('Rejected'),
            UserVerificationStatus::Unverified => __('Corrections requested'),
            default => str_replace('_', ' ', $status->value),
        };
    }
}
