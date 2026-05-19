<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StaffBulkMessageRequest extends Model
{
    protected $fillable = [
        'uuid',
        'created_by_admin_id',
        'approved_by_admin_id',
        'status',
        'audience',
        'channels',
        'subject',
        'body',
        'recipients_count',
        'approval_note',
        'approved_at',
        'dispatched_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (StaffBulkMessageRequest $request): void {
            $request->uuid ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'approved_at' => 'datetime',
            'dispatched_at' => 'datetime',
        ];
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_admin_id');
    }

    public function approvedByAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_admin_id');
    }
}
