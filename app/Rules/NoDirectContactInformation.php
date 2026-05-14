<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoDirectContactInformation implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $v = $value;

        if (preg_match('/[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}/', $v)) {
            $fail(__('Email addresses are not allowed. Keep all contact on HustleSafe — sharing personal contact can lead to a ban.'));

            return;
        }

        if (preg_match('/(?:\+?234|0)\s*[789][01]\s*\d{3}\s*\d{3}\s*\d{4}/', preg_replace('/\s+/', '', $v))
            || preg_match('/\b0[789][01]\d{8}\b/', preg_replace('/\s+/', '', $v))) {
            $fail(__('Phone numbers are not allowed in messages. Use in-app chat only.'));

            return;
        }

        if (preg_match('/\b(?:whatsapp|wa\.me|telegram|t\.me|signal|viber|dm\s+me|slide\s+into|hit\s+me\s+up|call\s+me|text\s+me)\b/i', $v)) {
            $fail(__('Off-platform contact requests are not allowed.'));

            return;
        }

        if (preg_match('/(?:instagram|facebook|fb\.com|twitter|x\.com|tiktok|linkedin)\s*\.?\s*com/i', $v)
            || preg_match('/@[a-z0-9._]{3,}/i', $v)) {
            $fail(__('Social handles and external profile links are not allowed.'));

            return;
        }
    }
}
