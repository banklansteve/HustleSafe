<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class WordCountBetween implements ValidationRule
{
    public function __construct(
        protected int $min,
        protected int $max,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail(__('The :attribute must be text.'));

            return;
        }

        $count = $this->wordCount($value);

        if ($count < $this->min || $count > $this->max) {
            $fail(__('The :attribute must be between :min and :max words.', [
                'min' => $this->min,
                'max' => $this->max,
            ]));
        }
    }

    protected function wordCount(string $text): int
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        if ($normalized === '') {
            return 0;
        }

        return count(preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
