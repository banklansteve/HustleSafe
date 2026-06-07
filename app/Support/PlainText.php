<?php

namespace App\Support;

use Illuminate\Support\Str;

final class PlainText
{
    /**
     * Convert HTML, markdown-ish markup, or rich text into readable plain text for admin UIs.
     */
    public static function from(?string $value, ?int $limit = null): string
    {
        if ($value === null || trim($value) === '') {
            return '';
        }

        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = strip_tags($text);
        $text = preg_replace('/!\[([^\]]*)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;
        $text = preg_replace('/`{1,3}([^`]+)`{1,3}/', '$1', $text) ?? $text;
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;
        $text = preg_replace('/[*_~]+/', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', trim($text)) ?? trim($text);

        if ($limit !== null && $limit > 0) {
            return Str::limit($text, $limit);
        }

        return $text;
    }
}
