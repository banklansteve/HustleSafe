<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdminRiskRule extends Model
{
    protected $fillable = [
        'name',
        'category',
        'severity',
        'is_active',
        'conditions',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'conditions' => 'array',
        ];
    }
}
