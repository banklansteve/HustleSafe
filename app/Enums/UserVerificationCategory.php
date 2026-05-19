<?php

namespace App\Enums;

enum UserVerificationCategory: string
{
    case Email = 'email';
    case Identity = 'identity';
    case Address = 'address';
    case IdentityAddress = 'identity_address';
    case Nin = 'nin';
    case Bvn = 'bvn';
    case Cac = 'cac';
    case Tin = 'tin';
    case ProfessionalCertificate = 'professional_certificate';
    case PortfolioReview = 'portfolio_review';
    case Qualification = 'qualification';

    /** CAC registration and business identity verification for company accounts. */
    case Business = 'business';

    /** Selfie holding an approved ID — required for high-value quest proposals after document ID is approved. */
    case LivePresence = 'live_presence';
}
