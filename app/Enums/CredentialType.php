<?php

namespace App\Enums;

enum CredentialType: string
{
    case Certification = 'certification';
    case Qualification = 'qualification';
    case ProfessionalLicence = 'professional_licence';
    case Insurance = 'insurance';

    public function label(): string
    {
        return match ($this) {
            self::Certification => __('Certification'),
            self::Qualification => __('Qualification'),
            self::ProfessionalLicence => __('Professional licence'),
            self::Insurance => __('Insurance'),
        };
    }

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        return self::cases();
    }
}
