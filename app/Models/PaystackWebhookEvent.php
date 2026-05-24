<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaystackWebhookEvent extends Model
{
    protected $fillable = [
        'event_id',
        'event_type',
        'reference',
        'payload',
        'processed_at',
        'processing_result',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
