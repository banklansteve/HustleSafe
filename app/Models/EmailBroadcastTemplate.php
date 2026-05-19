<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailBroadcastTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'created_by_admin_id',
        'name',
        'category',
        'suggested_audience',
        'subject',
        'preview_text',
        'body_html',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }
}
