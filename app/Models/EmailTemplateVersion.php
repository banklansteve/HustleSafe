<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateVersion extends Model
{
    protected $fillable = [
        'email_template_id',
        'created_by',
        'subject',
        'preheader',
        'blocks',
        'theme',
        'variables',
        'change_note',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'theme' => 'array',
            'variables' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
