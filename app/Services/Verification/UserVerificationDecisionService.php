<?php

namespace App\Services\Verification;

use App\Enums\UserVerificationCategory;
use App\Enums\UserVerificationStatus;
use App\Models\AdminTask;
use App\Models\User;
use App\Models\UserVerification;
use App\Notifications\UserVerificationDecisionNotification;
use App\Services\AdminActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class UserVerificationDecisionService
{
    public function __construct(
        private readonly VerificationEngineService $engine,
        private readonly AdminActivityLogger $logger,
        private readonly UserVerificationPresentationService $presentation,
        private readonly VerificationDecisionReasonService $reasons,
        private readonly IdentityDocumentUniquenessService $identityUniqueness,
    ) {}

    /**
     * @param  array{action: string, reason?: ?string, reason_code?: ?string, reason_note?: ?string}  $data
     * @return array{presentation: array<string, mixed>, message: string}
     */
    public function decide(UserVerification $verification, User $actor, array $data, Request $request, string $auditPrefix = 'verification'): array
    {
        $action = $data['action'];
        $requiresReason = in_array($action, ['reject', 'request_corrections'], true);
        $resolved = $this->reasons->resolve(
            $data['reason_code'] ?? null,
            $data['reason_note'] ?? $data['reason'] ?? null,
            $requiresReason,
        );

        $reasonDisplay = $resolved['display'] ?? trim((string) ($data['reason'] ?? ''));

        if ($requiresReason && $reasonDisplay === '' && strlen(trim((string) ($data['reason'] ?? ''))) < 8) {
            throw ValidationException::withMessages(['reason_code' => __('Select a reason for this decision.')]);
        }

        if ($requiresReason && ($data['reason_code'] ?? '') === '' && strlen(trim((string) ($data['reason'] ?? ''))) >= 8) {
            $reasonDisplay = trim((string) $data['reason']);
        }

        $status = match ($action) {
            'approve' => UserVerificationStatus::Verified,
            'reject' => UserVerificationStatus::Rejected,
            'request_corrections' => UserVerificationStatus::Unverified,
            default => throw ValidationException::withMessages(['action' => __('Invalid decision action.')]),
        };

        $old = $verification->only(['status', 'reviewed_by', 'reviewed_at', 'rejection_reason', 'decision_reason_code', 'decision_reason_note', 'admin_concern']);
        $patch = [
            'status' => $status,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'rejection_reason' => $action === 'approve' ? null : $reasonDisplay,
            'admin_concern' => $action === 'approve' ? null : $verification->admin_concern,
        ];

        if (Schema::hasColumn('user_verifications', 'decision_reason_code')) {
            $patch['decision_reason_code'] = $action === 'approve' ? null : ($resolved['code'] ?: null);
            $patch['decision_reason_note'] = $action === 'approve' ? null : ($resolved['note'] ?: null);
        }

        $verification->forceFill($this->reviewPatch($patch))->save();

        $verification->loadMissing('user');
        if ($action === 'approve' && $verification->user) {
            $this->registerApprovedIdentity($verification);
        }

        if ($verification->user) {
            $this->engine->recalculate($verification->user, $actor, 'Verification decision.');
        }

        $auditNew = $verification->only(['status', 'rejection_reason', 'decision_reason_code', 'decision_reason_note']);
        $auditReason = $action === 'approve'
            ? ($resolved['note'] ?: 'Approved')
            : ($resolved['label'] ? "{$resolved['label']}".($resolved['note'] ? " — {$resolved['note']}" : '') : $reasonDisplay);

        $this->engine->audit($actor, $verification->user, "{$auditPrefix}.{$action}", $old, $auditNew, $auditReason, $verification);
        $this->logger->log($actor, "{$auditPrefix}.{$action}", UserVerification::class, $verification->id, [
            'action' => $action,
            'reason_code' => $resolved['code'] ?? null,
            'reason_note' => $resolved['note'] ?? null,
            'reason' => $reasonDisplay,
        ], $request);

        if ($verification->user) {
            $verification->user->notify(new UserVerificationDecisionNotification(
                $verification->fresh(),
                $action,
                $reasonDisplay,
                $resolved['code'] ?? null,
                $resolved['label'] ?? null,
            ));
        }

        $fresh = $verification->fresh(['user', 'reviewer']);

        return [
            'presentation' => $this->presentation->forReview($fresh, $this->documentRouteForActor($actor)),
            'message' => match ($action) {
                'approve' => __('Verification approved. The user has been notified.'),
                'reject' => __('Verification rejected. The user has been notified.'),
                'request_corrections' => __('Correction request sent. The user has been notified.'),
                default => __('Decision saved.'),
            },
        ];
    }

    private function registerApprovedIdentity(UserVerification $verification): void
    {
        $user = $verification->user;
        if ($user === null) {
            return;
        }

        if (in_array($verification->category, [UserVerificationCategory::Nin, UserVerificationCategory::Bvn], true)) {
            $value = (string) data_get($verification->metadata, 'identifier_number', '');

            if ($verification->category === UserVerificationCategory::Bvn && filled($verification->encrypted_identifier)) {
                try {
                    $value = \Illuminate\Support\Facades\Crypt::decryptString((string) $verification->encrypted_identifier);
                } catch (\Throwable) {
                    $value = '';
                }
            }

            if ($value !== '') {
                $this->identityUniqueness->assertAvailableForUser($user, $verification->category->value, $value);
                $this->identityUniqueness->registerForUser($user, $verification->category->value, $value);
            }

            return;
        }

        if ($verification->category === UserVerificationCategory::IdentityAddress) {
            $idType = (string) data_get($verification->metadata, 'id_type', '');
            $value = (string) data_get($verification->metadata, 'identifier_number', '');

            if ($idType !== '' && $value !== '') {
                $this->identityUniqueness->assertAvailableForUser($user, $idType, $value);
                $this->identityUniqueness->registerForUser($user, $idType, $value);
            }
        }
    }

    private function documentRouteForActor(User $actor): string
    {
        return in_array($actor->role?->slug, ['admin', 'super_admin'], true)
            ? 'admin.user-verifications.document'
            : 'operations.api.verifications.document';
    }

    /**
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function reviewPatch(array $patch): array
    {
        foreach (['admin_concern', 'referred_to_admin_id', 'referred_at', 'decision_reason_code', 'decision_reason_note'] as $column) {
            if (! Schema::hasColumn('user_verifications', $column)) {
                unset($patch[$column]);
            }
        }

        return $patch;
    }

    private function completeEscalationTasks(UserVerification $verification): void
    {
        if (! Schema::hasTable('admin_tasks')) {
            return;
        }

        AdminTask::query()
            ->where('source_type', UserVerification::class)
            ->where('source_id', $verification->id)
            ->whereIn('status', ['todo', 'in_progress'])
            ->update([
                'status' => 'done',
                'completed_at' => now(),
            ]);
    }
}
