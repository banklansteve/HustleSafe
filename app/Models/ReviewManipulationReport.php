<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewManipulationReport extends Model
{
    protected $fillable = [
        'report_type',
        'report_date',
        'payload',
        'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'payload' => 'array',
            'generated_at' => 'datetime',
        ];
    }
}
