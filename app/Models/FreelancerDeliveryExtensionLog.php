<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreelancerDeliveryExtensionLog extends Model
{
    protected $fillable = [
        'user_id',
        'quest_contract_id',
        'delivery_extension_id',
        'outcome',
        'reason_category',
        'logged_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(QuestContract::class, 'quest_contract_id');
    }

    public function extension(): BelongsTo
    {
        return $this->belongsTo(QuestContractDeliveryExtension::class, 'delivery_extension_id');
    }
}
