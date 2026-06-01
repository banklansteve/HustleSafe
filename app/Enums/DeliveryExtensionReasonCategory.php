<?php

namespace App\Enums;

enum DeliveryExtensionReasonCategory: string
{
    case ScopeLargerThanEstimated = 'scope_larger_than_estimated';
    case PersonalOrHealth = 'personal_or_health';
    case ClientRequestedChanges = 'client_requested_changes';
    case TechnicalOrAccess = 'technical_or_access';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ScopeLargerThanEstimated => __('Scope was larger than estimated'),
            self::PersonalOrHealth => __('Personal or health circumstances'),
            self::ClientRequestedChanges => __('Client-requested changes increased workload'),
            self::TechnicalOrAccess => __('Technical or access issues'),
            self::Other => __('Other'),
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return collect(self::cases())->map(fn (self $c) => [
            'value' => $c->value,
            'label' => $c->label(),
        ])->all();
    }
}
