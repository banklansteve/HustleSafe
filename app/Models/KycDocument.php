<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KycDocument extends Model
{
    protected $fillable = [
        'kyc_review_case_id',
        'label',
        'document_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(KycReviewCase::class, 'kyc_review_case_id');
    }

    public function temporaryUrl(): string
    {
        $disk = $this->disk ?: 'local';

        try {
            return Storage::disk($disk)->temporaryUrl($this->path, now()->addMinutes(10));
        } catch (\Throwable) {
            return route('admin.kyc.documents.show', $this);
        }
    }
}
