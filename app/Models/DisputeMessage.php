<?php

namespace App\Models;

use App\Enums\DisputeMessageKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    protected $fillable = [
        'quest_dispute_id',
        'user_id',
        'kind',
        'body',
        'structured_key',
        'structured_payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DisputeMessageKind::class,
            'structured_payload' => 'array',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
