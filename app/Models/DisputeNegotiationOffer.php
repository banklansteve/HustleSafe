<?php

namespace App\Models;

use App\Enums\DisputeNegotiationOfferStatus;
use App\Enums\DisputeResolutionOption;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisputeNegotiationOffer extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'parent_offer_id',
        'offered_by_user_id',
        'party_role',
        'attempt_number',
        'option',
        'terms',
        'status',
        'is_final_offer',
        'awaiting_party_role',
        'response_required_by',
        'responded_at',
        'responded_by_user_id',
        'response_action',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'terms' => 'array',
            'is_final_offer' => 'boolean',
            'response_required_by' => 'datetime',
            'responded_at' => 'datetime',
            'status' => DisputeNegotiationOfferStatus::class,
        ];
    }

    /**
     * @return BelongsTo<QuestDispute, $this>
     */
    public function dispute(): BelongsTo
    {
        return $this->belongsTo(QuestDispute::class, 'quest_dispute_id');
    }

    /**
     * @return BelongsTo<DisputeNegotiationOffer, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_offer_id');
    }

    /**
     * @return HasMany<DisputeNegotiationOffer, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function offeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'offered_by_user_id');
    }

    public function optionEnum(): ?DisputeResolutionOption
    {
        return DisputeResolutionOption::tryFrom((string) $this->option);
    }

    public function summaryLabel(): string
    {
        $enum = $this->optionEnum();
        $label = $enum?->label() ?? $this->option;

        if (isset($this->terms['client_share_percent'])) {
            return "{$label} ({$this->terms['client_share_percent']}% client)";
        }

        return $label;
    }
}
