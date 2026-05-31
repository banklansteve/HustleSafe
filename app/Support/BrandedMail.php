<?php

namespace App\Support;

final class BrandedMail
{
    public static function brandName(): string
    {
        return (string) config('app.name', 'HustleSafe');
    }

    public static function firstName(object $notifiable): string
    {
        return (string) ($notifiable->first_name ?: $notifiable->name ?: __('there'));
    }

    public static function logoUrl(): string
    {
        return url('/images/logo/v7b_lockup_light.png');
    }

    public static function appUrl(): string
    {
        return (string) config('app.url', url('/'));
    }

    /**
     * @return array<string, string>
     */
    public static function theme(): array
    {
        return [
            'primary' => '#0f766e',
            'primary_light' => '#14b8a6',
            'surface' => '#f0fdfa',
            'border' => '#99f6e4',
            'text' => '#334155',
            'heading' => '#0f172a',
        ];
    }
}
