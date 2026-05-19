<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    protected $fillable = [
        'key',
        'trigger_event',
        'name',
        'subject',
        'preheader',
        'blocks',
        'theme',
        'variables',
        'is_active',
        'last_edited_by',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'theme' => 'array',
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(EmailTemplateVersion::class)->latest();
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(EmailTemplateAnalytic::class);
    }
}
