<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HelpSection extends Model
{
    protected $fillable = ['title', 'slug', 'display_order', 'status'];

    public function faqs(): HasMany
    {
        return $this->hasMany(HelpFaqItem::class)->orderBy('display_order')->orderBy('id');
    }
}
