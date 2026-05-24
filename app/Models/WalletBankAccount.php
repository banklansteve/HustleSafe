<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletBankAccount extends Model
{
    protected $fillable = [
        'user_id',
        'bank_code',
        'bank_name',
        'account_number',
        'account_name',
        'paystack_recipient_code',
        'is_default',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function maskedAccountNumber(): string
    {
        $n = $this->account_number;
        if (strlen($n) <= 4) {
            return $n;
        }

        return str_repeat('•', max(0, strlen($n) - 4)).substr($n, -4);
    }
}
