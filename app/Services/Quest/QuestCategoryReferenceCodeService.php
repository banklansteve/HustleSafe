<?php

namespace App\Services\Quest;

use App\Models\QuestCategory;
use Illuminate\Support\Str;

class QuestCategoryReferenceCodeService
{
    /**
     * Three-character parent category codes for quest reference IDs.
     *
     * @var array<string, string>
     */
    private const PARENT_PREFIX_BY_SLUG = [
        'home-office-cleaning' => 'CLN',
        'repairs-maintenance' => 'RPR',
        'automotive-services' => 'AUT',
        'installation-assembly' => 'INS',
        'beauty-personal-care' => 'BTY',
        'laundry-textile-care' => 'LDY',
        'catering-food-services' => 'FUD',
        'transportation-logistics' => 'LOG',
        'childcare-eldercare' => 'CRE',
        'specialized-artisan' => 'ART',
        'technology-software' => 'TEC',
        'design-creative' => 'DES',
        'writing-content' => 'WRT',
        'marketing-growth' => 'MKT',
        'business-operations' => 'BUS',
        'finance-accounting' => 'FIN',
        'legal-compliance' => 'LEG',
        'engineering-stem' => 'ENG',
        'trades-field' => 'TRD',
        'education-training' => 'EDU',
        'healthcare-wellness' => 'HLT',
        'media-events' => 'MED',
        'sales-bd' => 'SAL',
        'ecommerce-retail' => 'ECO',
        'agriculture-supply' => 'AGR',
        'real-estate' => 'RLD',
        'nonprofit-community' => 'NGO',
        'gaming-interactive' => 'GAM',
        'research-decision' => 'RES',
        'other-multidisciplinary' => 'OTH',
    ];

    public function prefixForCategoryId(?int $questCategoryId): string
    {
        if ($questCategoryId === null || $questCategoryId <= 0) {
            return 'GEN';
        }

        $category = QuestCategory::query()
            ->with('parent:id,slug')
            ->find($questCategoryId);

        if ($category === null) {
            return 'GEN';
        }

        $parentSlug = $category->parent_id
            ? (string) ($category->parent?->slug ?? '')
            : (string) $category->slug;

        if ($parentSlug === '') {
            return 'GEN';
        }

        if (isset(self::PARENT_PREFIX_BY_SLUG[$parentSlug])) {
            return self::PARENT_PREFIX_BY_SLUG[$parentSlug];
        }

        $derived = strtoupper(Str::substr(preg_replace('/[^a-z0-9]+/i', '', $parentSlug) ?? '', 0, 3));

        return strlen($derived) === 3 ? $derived : 'GEN';
    }
}
