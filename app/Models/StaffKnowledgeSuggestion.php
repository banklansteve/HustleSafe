<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffKnowledgeSuggestion extends Model
{
    protected $fillable = [
        'staff_knowledge_article_id',
        'suggested_by_staff_id',
        'body',
        'status',
    ];

    public function article(): BelongsTo
    {
        return $this->belongsTo(StaffKnowledgeArticle::class, 'staff_knowledge_article_id');
    }

    public function suggester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suggested_by_staff_id');
    }
}
