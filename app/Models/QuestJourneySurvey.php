<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QuestJourneySurvey extends Model
{
    protected $fillable = [
        'token',
        'quest_id',
        'user_id',
        'quest_offer_id',
        'cohort',
        'rejection_reason',
        'answers',
        'first_question_key',
        'first_answer_value',
        'first_answer_at',
        'submitted_at',
        'expires_at',
        'email_send_at',
        'email_sent_at',
        'reminders_sent',
        'operational_flagged',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
            'first_answer_at' => 'datetime',
            'submitted_at' => 'datetime',
            'expires_at' => 'datetime',
            'email_send_at' => 'datetime',
            'email_sent_at' => 'datetime',
            'reminders_sent' => 'array',
            'operational_flagged' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuestJourneySurvey $survey): void {
            if ($survey->token === null) {
                $survey->token = (string) Str::uuid();
            }
        });
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isOpen(): bool
    {
        return ! $this->isExpired() && ! $this->isSubmitted();
    }

    /**
     * @return BelongsTo<Quest, $this>
     */
    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<QuestOffer, $this>
     */
    public function questOffer(): BelongsTo
    {
        return $this->belongsTo(QuestOffer::class);
    }
}
