<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffProactiveOutreachLog extends Model
{
    protected $fillable = [
        'outreach_item_id',
        'staff_user_id',
        'template_id',
        'channel',
        'subject',
        'body',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StaffProactiveOutreachItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(StaffProactiveOutreachItem::class, 'outreach_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }

    /**
     * @return BelongsTo<StaffResponseTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(StaffResponseTemplate::class, 'template_id');
    }
}
