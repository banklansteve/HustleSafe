<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminSavedReport extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'report_type',
        'builder_config',
        'filters',
        'date_preset',
        'date_from',
        'date_to',
        'schedule_frequency',
        'schedule_recipients',
        'last_run_at',
        'next_run_at',
    ];

    protected function casts(): array
    {
        return [
            'builder_config' => 'array',
            'filters' => 'array',
            'schedule_recipients' => 'array',
            'date_from' => 'date',
            'date_to' => 'date',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function exports(): HasMany
    {
        return $this->hasMany(AdminReportExport::class);
    }
}
