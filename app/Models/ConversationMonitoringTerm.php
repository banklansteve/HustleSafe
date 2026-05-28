<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversationMonitoringTerm extends Model
{
    protected $fillable = [
        'term_type',
        'pattern',
        'is_wildcard',
        'is_active',
        'locale_hint',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_wildcard' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
