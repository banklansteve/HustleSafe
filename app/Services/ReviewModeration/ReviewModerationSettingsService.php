<?php

namespace App\Services\ReviewModeration;

use App\Models\AdminPlatformSetting;
use Illuminate\Support\Facades\Schema;

class ReviewModerationSettingsService
{
    /**
     * @return array<string, string>
     */
    public function defaultActionsByFlag(): array
    {
        $defaults = config('review_moderation.amendment.default_actions', []);
        $keys = [
            'velocity_cluster' => 'review_moderation.default_action.velocity_cluster',
            'sentiment_mismatch' => 'review_moderation.default_action.sentiment_mismatch',
            'reciprocal_pair' => 'review_moderation.default_action.reciprocal_pair',
            'ip_cluster' => 'review_moderation.default_action.ip_cluster',
            'blacklisted_keyword' => 'review_moderation.default_action.blacklisted_keyword',
        ];

        $resolved = [];
        foreach ($keys as $flag => $settingKey) {
            $value = $this->readSetting($settingKey);
            $resolved[$flag] = in_array($value, ['auto_publish', 'auto_remove'], true)
                ? $value
                : ($defaults[$flag] ?? 'auto_remove');
        }

        return $resolved;
    }

    private function readSetting(string $key): ?string
    {
        if (! Schema::hasTable('admin_platform_settings')) {
            return null;
        }

        $record = AdminPlatformSetting::query()->where('key', $key)->first();
        $value = $record?->value['value'] ?? null;

        return is_string($value) ? $value : null;
    }

    public function defaultActionFor(string $flagType): string
    {
        $map = $this->defaultActionsByFlag();

        return $map[$flagType] ?? 'auto_remove';
    }
}
