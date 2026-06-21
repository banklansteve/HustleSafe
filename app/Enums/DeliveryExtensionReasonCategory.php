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
            self::ScopeLargerThanEstimated => __('The job turned out bigger than expected'),
            self::PersonalOrHealth => __('Personal or health issue'),
            self::ClientRequestedChanges => __('The client asked for extra work'),
            self::TechnicalOrAccess => __('Could not access site or materials on time'),
            self::Other => __('Other reason'),
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
