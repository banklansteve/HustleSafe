<?php

namespace App\Services\Contracts;

use App\Enums\ContractAmendmentType;
use App\Enums\ContractStatus;
use App\Models\QuestContract;
use App\Models\QuestContractAmendment;
use App\Models\User;
use App\Notifications\ContractAmendmentRequestedNotification;
use App\Notifications\ContractAmendmentRespondedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ContractAmendmentService
{
    public const MAX_AMENDMENTS = 3;

    public function __construct(
        private readonly ContractLifecycleService $lifecycle,
        private readonly ContractEventLogger $events,
    ) {}

    /**
     * @param  array{amendment_type: string, description: string, reason: string, new_value?: string|null}  $data
     */
    public function request(QuestContract $contract, User $requester, array $data, ?Request $request = null): QuestContractAmendment
    {
        if (! in_array($contract->status, [ContractStatus::Active, ContractStatus::AmendmentPending], true)) {
            throw ValidationException::withMessages(['contract' => __('Amendments can only be requested on active contracts.')]);
        }

        if (! $contract->isParty($requester)) {
            abort(403);
        }

        if ($contract->amendment_count >= self::MAX_AMENDMENTS) {
            throw ValidationException::withMessages(['contract' => __('This contract has reached the amendment limit.')]);
        }

        if ($contract->amendments()->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['contract' => __('An amendment is already awaiting response.')]);
        }

        $type = ContractAmendmentType::from($data['amendment_type']);
        $original = $this->originalValueFor($contract, $type);

        return DB::transaction(function () use ($contract, $requester, $data, $type, $original, $request): QuestContractAmendment {
            $amendment = QuestContractAmendment::query()->create([
                'quest_contract_id' => $contract->id,
                'amendment_number' => $contract->amendment_count + 1,
                'requested_by_user_id' => $requester->id,
                'amendment_type' => $type,
                'description' => $data['description'],
                'reason' => $data['reason'],
                'original_value' => $original,
                'new_value' => $data['new_value'] ?? null,
                'status' => 'pending',
            ]);

            $contract->update(['status' => ContractStatus::AmendmentPending]);
            $this->events->log($contract, 'contract.amendment_requested', $requester, [
                'amendment_id' => $amendment->id,
                'type' => $type->value,
            ], $request);

            $counterparty = (int) $requester->id === (int) $contract->client_id
                ? $contract->freelancer
                : $contract->client;
            $counterparty?->notify(new ContractAmendmentRequestedNotification($contract, $amendment));

            return $amendment;
        });
    }

    public function accept(QuestContract $contract, QuestContractAmendment $amendment, User $responder, ?Request $request = null): void
    {
        $this->assertCanRespond($contract, $amendment, $responder);

        DB::transaction(function () use ($contract, $amendment, $responder, $request): void {
            $delta = $this->buildTermsDelta($contract, $amendment);

            $amendment->update([
                'status' => 'accepted',
                'responded_by_user_id' => $responder->id,
                'responded_at' => now(),
                'applied_terms_delta' => $delta,
            ]);

            $current = $contract->current_terms_snapshot ?? [];
            $contract->update([
                'status' => ContractStatus::Active,
                'amendment_count' => $contract->amendment_count + 1,
                'current_terms_snapshot' => array_replace_recursive($current, $delta),
                'agreed_delivery_date' => $delta['timeline']['agreed_delivery_date'] ?? $contract->agreed_delivery_date,
            ]);

            if (isset($delta['financial']['total_minor'])) {
                $financial = $contract->financial_snapshot;
                $financial = array_merge($financial, $delta['financial']);
                $contract->update(['financial_snapshot' => $financial]);
            }

            $this->events->log($contract, 'contract.amendment_accepted', $responder, [
                'amendment_id' => $amendment->id,
            ], $request);

            $amendment->requester?->notify(new ContractAmendmentRespondedNotification($contract, $amendment, true));
        });
    }

    public function decline(QuestContract $contract, QuestContractAmendment $amendment, User $responder, string $note, ?Request $request = null): void
    {
        $this->assertCanRespond($contract, $amendment, $responder);

        if (trim($note) === '') {
            throw ValidationException::withMessages(['response_note' => __('Please provide a note when declining an amendment.')]);
        }

        DB::transaction(function () use ($contract, $amendment, $responder, $note, $request): void {
            $amendment->update([
                'status' => 'declined',
                'response_note' => $note,
                'responded_by_user_id' => $responder->id,
                'responded_at' => now(),
            ]);

            $contract->update([
                'status' => ContractStatus::Active,
                'amendment_count' => $contract->amendment_count + 1,
            ]);

            $this->events->log($contract, 'contract.amendment_declined', $responder, [
                'amendment_id' => $amendment->id,
                'note' => $note,
            ], $request);

            $amendment->requester?->notify(new ContractAmendmentRespondedNotification($contract, $amendment, false));
        });
    }

    private function assertCanRespond(QuestContract $contract, QuestContractAmendment $amendment, User $responder): void
    {
        if ((int) $amendment->quest_contract_id !== (int) $contract->id || $amendment->status !== 'pending') {
            throw ValidationException::withMessages(['amendment' => __('This amendment is not awaiting a response.')]);
        }

        if ((int) $amendment->requested_by_user_id === (int) $responder->id) {
            throw ValidationException::withMessages(['amendment' => __('You cannot respond to your own amendment request.')]);
        }

        if (! $contract->isParty($responder)) {
            abort(403);
        }
    }

    private function originalValueFor(QuestContract $contract, ContractAmendmentType $type): ?string
    {
        $terms = $contract->effectiveTerms();

        return match ($type) {
            ContractAmendmentType::Scope => $terms['quest']['scope_description'] ?? null,
            ContractAmendmentType::Price => $terms['financial']['total_label'] ?? null,
            ContractAmendmentType::DeliveryDate => $terms['timeline']['agreed_delivery_label'] ?? null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTermsDelta(QuestContract $contract, QuestContractAmendment $amendment): array
    {
        return match ($amendment->amendment_type) {
            ContractAmendmentType::Scope => [
                'quest' => [
                    'scope_description' => $amendment->description,
                ],
            ],
            ContractAmendmentType::Price => [
                'financial' => $this->priceDelta($amendment->new_value),
            ],
            ContractAmendmentType::DeliveryDate => [
                'timeline' => [
                    'agreed_delivery_date' => $amendment->new_value,
                    'agreed_delivery_label' => $amendment->new_value
                        ? \Carbon\Carbon::parse($amendment->new_value)->format('j M Y')
                        : null,
                ],
            ],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function priceDelta(?string $newValue): array
    {
        $digits = preg_replace('/[^\d]/', '', (string) $newValue) ?: '0';
        $minor = (int) $digits * 100;

        return [
            'total_minor' => $minor,
            'total_label' => '₦'.number_format($minor / 100, 0),
        ];
    }
}
