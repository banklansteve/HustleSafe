<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminDirectMessage extends Model
{
    protected $fillable = [
        'admin_direct_conversation_id',
        'user_id',
        'body',
        'attachments',
        'mentions',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'mentions' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AdminDirectConversation::class, 'admin_direct_conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(AdminDirectMessageReceipt::class);
    }
}
