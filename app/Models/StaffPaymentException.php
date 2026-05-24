<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffPaymentException extends Model
{
    protected $fillable = [
        'uuid',
        'staff_user_id',
        'user_id',
        'quest_id',
        'admin_task_id',
        'type',
        'status',
        'amount_minor',
        'error_code',
        'error_summary',
        'staff_summary',
        'metadata',
        'resolved_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffPaymentException $row): void {
            $row->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    public function adminTask(): BelongsTo
    {
        return $this->belongsTo(AdminTask::class);
    }
}
