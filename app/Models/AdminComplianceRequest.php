<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AdminComplianceRequest extends Model
{
    protected $fillable = [
        'user_id',
        'assigned_to_admin_id',
        'request_type',
        'status',
        'reference',
        'requester_note',
        'admin_note',
        'due_at',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (AdminComplianceRequest $request): void {
            $request->reference ??= 'NDR-'.now()->format('ymd').'-'.Str::upper(Str::random(6));
            $request->due_at ??= now()->addDays(30);
        });
    }

    protected function casts(): array
    {
        return [
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_admin_id');
    }
}
