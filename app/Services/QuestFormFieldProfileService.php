<?php

namespace App\Services;

use App\Models\QuestCategory;

/**
 * Drives which quest form sections apply for a given category (parent + leaf slugs).
 */
class QuestFormFieldProfileService
{
    /**
     * Parent slugs where on-site visits are commonly relevant.
     *
     * @var list<string>
     */
    protected const SITE_VISIT_PARENTS = [
        'trades-field',
        'real-estate',
        'agriculture-supply',
        'media-events',
        'engineering-stem',
    ];

    /**
     * Parent slugs where availability (FT/PT/as-needed) is surfaced.
     *
     * @var list<string>
     */
    protected const AVAILABILITY_PARENTS = [
        'technology-software',
        'marketing-growth',
        'business-operations',
        'engineering-stem',
        'trades-field',
        'healthcare-wellness',
        'sales-bd',
        'ecommerce-retail',
    ];

    /**
     * @return array{
     *   show_site_visit: bool,
     *   show_availability: bool,
     *   show_hourly_fields: bool,
     *   show_team_size: bool,
     *   default_site_visits: bool,
     *   parent_slug: ?string,
     *   leaf_slug: ?string
     * }
     */
    public function profileForLeafCategoryId(?int $leafId): array
    {
        if ($leafId === null || $leafId < 1) {
            return $this->emptyProfile();
        }

        $leaf = QuestCategory::query()
            ->with('parent:id,slug')
            ->find($leafId);

        if ($leaf === null || $leaf->parent_id === null) {
            return $this->emptyProfile();
        }

        $parentSlug = $leaf->parent?->slug;
        $leafSlug = $leaf->slug;

        $showSiteVisit = $parentSlug !== null && in_array($parentSlug, self::SITE_VISIT_PARENTS, true);
        $showAvailability = $parentSlug !== null && in_array($parentSlug, self::AVAILABILITY_PARENTS, true);

        $digitalParents = ['technology-software', 'design-creative', 'writing-content', 'gaming-interactive'];
        $showHourlyFields = $parentSlug !== null && ! in_array($parentSlug, ['legal-compliance'], true);
        $showTeamSize = $parentSlug !== null && ! in_array($parentSlug, $digitalParents, true);

        return [
            'show_site_visit' => $showSiteVisit,
            'show_availability' => $showAvailability,
            'show_hourly_fields' => $showHourlyFields,
            'show_team_size' => $showTeamSize,
            'default_site_visits' => $showSiteVisit,
            'parent_slug' => $parentSlug,
            'leaf_slug' => $leafSlug,
        ];
    }

    /**
     * @return array<string, bool|string|null>
     */
    protected function emptyProfile(): array
    {
        return [
            'show_site_visit' => false,
            'show_availability' => true,
            'show_hourly_fields' => true,
            'show_team_size' => true,
            'default_site_visits' => false,
            'parent_slug' => null,
            'leaf_slug' => null,
        ];
    }
}
