<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailTemplateAnalytic extends Model
{
    protected $fillable = [
        'email_template_id',
        'metric_date',
        'sent_count',
        'open_count',
        'click_count',
        'unsubscribe_count',
        'provider',
    ];

    protected function casts(): array
    {
        return ['metric_date' => 'date'];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'email_template_id');
    }
}
