<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailBroadcast extends Model
{
    protected $fillable = [
        'template_id',
        'created_by_admin_id',
        'subject',
        'preview_text',
        'reply_to',
        'from_name',
        'body_html',
        'audience',
        'audience_description',
        'status',
        'total_recipients',
        'queued_count',
        'sent_count',
        'delivered_count',
        'opened_count',
        'clicked_count',
        'bounced_count',
        'unsubscribed_count',
        'scheduled_for',
        'sent_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'scheduled_for' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailBroadcastTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(EmailBroadcastRecipient::class);
    }
}
