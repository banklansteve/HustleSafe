<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDirectConversation extends Model
{
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'last_message_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AdminDirectMessage::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(AdminDirectMessage::class, 'last_message_id');
    }

    public function otherParticipant(int $userId): ?User
    {
        if ((int) $this->user_one_id === $userId) {
            return $this->userTwo;
        }
        if ((int) $this->user_two_id === $userId) {
            return $this->userOne;
        }

        return null;
    }

    public function includesUser(int $userId): bool
    {
        return (int) $this->user_one_id === $userId || (int) $this->user_two_id === $userId;
    }

    public static function canonicalPair(int $a, int $b): array
    {
        return $a < $b ? [$a, $b] : [$b, $a];
    }
}
