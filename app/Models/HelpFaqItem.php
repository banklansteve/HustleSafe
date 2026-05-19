<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class HelpFaqItem extends Model
{
    protected $fillable = [
        'help_section_id',
        'question',
        'answer',
        'audience',
        'search_keywords',
        'display_order',
        'status',
        'last_edited_by',
    ];

    protected function casts(): array
    {
        return ['search_keywords' => 'array'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(HelpSection::class, 'help_section_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function versions(): MorphMany
    {
        return $this->morphMany(ContentVersion::class, 'versionable')->latest();
    }
}
