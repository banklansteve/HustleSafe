<?php

namespace App\Services\Quest;

use App\Models\ProposalPreferenceResponse;
use App\Models\Quest;
use App\Models\QuestOffer;
use App\Models\QuestPreference;
use Illuminate\Validation\ValidationException;

class ProposalPreferenceResponseService
{
    public function __construct(
        protected QuestPreferenceService $preferences,
    ) {}

    /**
     * @param  array<string, array{response_type: string, response_text?: ?string}>  $submitted
     */
    public function syncForOffer(QuestOffer $offer, Quest $quest, array $submitted): void
    {
        $quest->loadMissing('preferences');
        $specified = $quest->preferences->where('is_specified', true);

        if ($specified->isEmpty()) {
            return;
        }

        foreach ($specified as $preference) {
            /** @var QuestPreference $preference */
            $row = $submitted[$preference->preference_key] ?? null;
            if (! is_array($row)) {
                throw ValidationException::withMessages([
                    "preference_responses.{$preference->preference_key}" => __('Please respond to this client preference.'),
                ]);
            }

            $type = (string) ($row['response_type'] ?? '');
            $text = trim((string) ($row['response_text'] ?? ''));

            if (! in_array($type, ['accept', 'propose_alternative', 'clarify', 'custom'], true)) {
                throw ValidationException::withMessages([
                    "preference_responses.{$preference->preference_key}.response_type" => __('Choose how you want to respond.'),
                ]);
            }

            if (in_array($type, ['propose_alternative', 'clarify', 'custom'], true) && $text === '') {
                throw ValidationException::withMessages([
                    "preference_responses.{$preference->preference_key}.response_text" => __('Please provide details for this response.'),
                ]);
            }

            $max = $type === 'accept' ? 300 : 500;

            ProposalPreferenceResponse::query()->updateOrCreate(
                [
                    'quest_offer_id' => $offer->id,
                    'quest_preference_id' => $preference->id,
                ],
                [
                    'response_type' => $type,
                    'response_text' => $text !== '' ? mb_substr($text, 0, $max) : null,
                ],
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function displayForOffer(QuestOffer $offer, Quest $quest): array
    {
        $quest->loadMissing('preferences');
        $profile = app(QuestPreferenceProfileService::class)->profileForLeafCategoryId((int) $quest->quest_category_id);
        $responses = $offer->relationLoaded('preferenceResponses')
            ? $offer->preferenceResponses->keyBy('quest_preference_id')
            : $offer->preferenceResponses()->get()->keyBy('quest_preference_id');

        $rows = [];
        foreach ($quest->preferences->where('is_specified', true) as $preference) {
            $definition = $profile['fields'][$preference->preference_key] ?? [];
            $response = $responses->get($preference->id);

            $rows[] = [
                'key' => $preference->preference_key,
                'label' => (string) ($definition['label'] ?? $preference->preference_key),
                'client_value' => $this->preferences->formatDisplayValue($preference->preference_value, $definition),
                'response_type' => $response?->response_type,
                'response_text' => $response?->response_text,
                'response_label' => $this->responseTypeLabel($response?->response_type),
                'response_badge' => $this->responseTypeBadge($response?->response_type),
            ];
        }

        return $rows;
    }

    protected function responseTypeLabel(?string $type): string
    {
        return match ($type) {
            'accept' => 'Accept',
            'propose_alternative' => 'Alternative',
            'clarify' => 'Clarify',
            'custom' => 'Custom',
            default => 'Not addressed',
        };
    }

    protected function responseTypeBadge(?string $type): string
    {
        return match ($type) {
            'accept' => 'accept',
            'propose_alternative' => 'alternative',
            'clarify' => 'clarify',
            'custom' => 'custom',
            default => 'none',
        };
    }
}
