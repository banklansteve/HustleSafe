<?php

namespace App\Services\Disputes;

use App\Enums\DisputeResolutionOption;

class DisputeResolutionMatrixService
{
    /**
     * @return list<array{value: string, label: string, hint: string, category: string, path: string, mutual: bool, requires_client_share: bool, requires_days: bool, optional_revision_days: bool, requires_target_date: bool, requires_terms_note: bool, default_client_share_percent: ?int, action: string}>
     */
    public function optionsForActor(string $actor): array
    {
        $allowed = match ($actor) {
            'staff' => $this->staffSuggestable(),
            'client' => $this->clientProposable(),
            'freelancer' => $this->freelancerProposable(),
            'super_admin' => $this->superAdminInitiable(),
            default => [],
        };

        return collect($allowed)
            ->map(fn (DisputeResolutionOption $option) => $this->optionPayload($option, $actor))
            ->values()
            ->all();
    }

    public function canActorUse(string $actor, string $option): bool
    {
        $enum = DisputeResolutionOption::tryFrom($option);
        if ($enum === null) {
            return false;
        }

        return in_array($enum, match ($actor) {
            'staff' => $this->staffSuggestable(),
            'client' => $this->clientProposable(),
            'freelancer' => $this->freelancerProposable(),
            'super_admin' => $this->superAdminInitiable(),
            default => [],
        }, true);
    }

    public function assertActorCanUse(string $actor, string $option): void
    {
        if (! $this->canActorUse($actor, $option)) {
            abort(422, __('You cannot propose this resolution option.'));
        }
    }

    /**
     * @return list<DisputeResolutionOption>
     */
    private function staffSuggestable(): array
    {
        return [
            DisputeResolutionOption::AwardClientFull,
            DisputeResolutionOption::AwardFreelancerFull,
            DisputeResolutionOption::SplitFund,
            DisputeResolutionOption::ForceRevision,
            DisputeResolutionOption::ExtendDeadline,
            DisputeResolutionOption::ReviseRedo,
            DisputeResolutionOption::ExtendDelivery,
            DisputeResolutionOption::AdjustTimeline,
            DisputeResolutionOption::ScopeAdjustment,
            DisputeResolutionOption::Other,
            DisputeResolutionOption::Mediation,
        ];
    }

    /**
     * @return list<DisputeResolutionOption>
     */
    private function clientProposable(): array
    {
        return [
            DisputeResolutionOption::SplitFund,
            DisputeResolutionOption::RefundCancel,
            DisputeResolutionOption::ReviseRedo,
            DisputeResolutionOption::ExtendDelivery,
            DisputeResolutionOption::AdjustTimeline,
            DisputeResolutionOption::ScopeAdjustment,
            DisputeResolutionOption::Other,
            DisputeResolutionOption::AwardClientFull,
            DisputeResolutionOption::ForceRevision,
            DisputeResolutionOption::Mediation,
        ];
    }

    /**
     * @return list<DisputeResolutionOption>
     */
    private function freelancerProposable(): array
    {
        return [
            DisputeResolutionOption::SplitFund,
            DisputeResolutionOption::RefundCancel,
            DisputeResolutionOption::ReviseRedo,
            DisputeResolutionOption::ExtendDelivery,
            DisputeResolutionOption::AdjustTimeline,
            DisputeResolutionOption::ScopeAdjustment,
            DisputeResolutionOption::Other,
            DisputeResolutionOption::AwardFreelancerFull,
            DisputeResolutionOption::ExtendDeadline,
            DisputeResolutionOption::Mediation,
        ];
    }

    /**
     * @return list<DisputeResolutionOption>
     */
    private function superAdminInitiable(): array
    {
        return DisputeResolutionOption::cases();
    }

    public function resolutionPath(DisputeResolutionOption $option): string
    {
        return $option->isMutual() ? 'together' : 'support';
    }

    /**
     * @return array{value: string, label: string, hint: string, category: string, path: string, mutual: bool, requires_client_share: bool, requires_days: bool, optional_revision_days: bool, requires_target_date: bool, requires_terms_note: bool, default_client_share_percent: ?int, action: string}
     */
    private function optionPayload(DisputeResolutionOption $option, string $actor): array
    {
        return [
            'value' => $option->value,
            'label' => $option->label(),
            'hint' => $option->partyHint(),
            'category' => $option->category(),
            'path' => $this->resolutionPath($option),
            'mutual' => $option->isMutual(),
            'requires_client_share' => $option->requiresClientShare(),
            'requires_days' => $option->requiresDays(),
            'optional_revision_days' => $option->optionalRevisionDays(),
            'requires_target_date' => $option->requiresTargetDate(),
            'requires_terms_note' => $option->requiresTermsNote(),
            'default_client_share_percent' => $option->defaultClientSharePercent(),
            'action' => match ($actor) {
                'staff' => 'recommend',
                'super_admin' => 'execute',
                default => 'propose',
            },
        ];
    }
}
