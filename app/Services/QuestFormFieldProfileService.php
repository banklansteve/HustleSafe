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
     *   show_site_access: bool,
     *   show_pets_question: bool,
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

        $showSiteAccess = $this->siteAccessContextApplies($parentSlug, $leafSlug);

        return [
            'show_site_visit' => $showSiteVisit,
            'show_site_access' => $showSiteAccess,
            'show_pets_question' => $showSiteAccess,
            'show_availability' => $showAvailability,
            'show_hourly_fields' => $showHourlyFields,
            'show_team_size' => $showTeamSize,
            'default_site_visits' => $showSiteVisit,
            'parent_slug' => $parentSlug,
            'leaf_slug' => $leafSlug,
        ];
    }

    /**
     * On-site access / pets only when the work is commonly performed at a client location
     * (trades, selected field services, in-person tutoring, events, etc.) — not for desk-only digital work.
     */
    protected function siteAccessContextApplies(?string $parentSlug, ?string $leafSlug): bool
    {
        if ($parentSlug === 'trades-field') {
            return true;
        }

        if ($leafSlug === null) {
            return false;
        }

        $leaves = [
            'photography',
            'videography-livestream',
            'event-planning',
            'tutoring-stem',
            'tutoring-languages',
            'estate-management',
            'nutrition-fitness',
            'farm-advisory',
        ];

        return in_array($leafSlug, $leaves, true);
    }

    /**
     * @return array<string, bool|string|null>
     */
    protected function emptyProfile(): array
    {
        return [
            'show_site_visit' => false,
            'show_site_access' => false,
            'show_pets_question' => false,
            'show_availability' => true,
            'show_hourly_fields' => true,
            'show_team_size' => true,
            'default_site_visits' => false,
            'parent_slug' => null,
            'leaf_slug' => null,
        ];
    }
}
