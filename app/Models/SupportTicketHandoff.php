<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportTicketHandoff extends Model
{
    protected $fillable = [
        'support_ticket_id',
        'from_admin_id',
        'to_admin_id',
        'reassigned_by_id',
        'handoff_message_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(SupportTicket::class, 'support_ticket_id');
    }

    public function fromAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_admin_id');
    }

    public function toAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_admin_id');
    }
}
