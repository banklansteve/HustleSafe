<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffKnowledgeArticle extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'category',
        'body',
        'status',
        'created_by_admin_id',
        'updated_by_admin_id',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function suggestions(): HasMany
    {
        return $this->hasMany(StaffKnowledgeSuggestion::class);
    }
}
