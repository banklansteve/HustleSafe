<?php

use App\Models\QuestCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Sync the expanded Nigerian service-space category tree into existing databases.
 *
 * Backward-compatible by design:
 *   - Upserts strictly by `slug`, so existing rows keep their id (quests and
 *     freelancer category picks reference `quest_category_id` and stay valid).
 *   - Only `name`, `parent_id`, and `sort_order` are updated on existing rows;
 *     admin-managed fields (description, icon, fees, guardrails, status/archive
 *     state) are preserved.
 *   - Re-parents a couple of existing subcategories (auto-mechanic →
 *     Automotive Services, landscaping-gardening → Home & Office Cleaning)
 *     without changing their id.
 *   - Never deletes categories.
 */
return new class extends Migration
{
    public function up(): void
    {
        $tree = require database_path('data/quest_category_tree.php');
        if (! is_array($tree)) {
            return;
        }

        foreach ($tree as $parentOrder => $parentRow) {
            $parent = $this->syncCategory($parentRow['slug'], null, $parentRow['name'], $parentOrder);

            foreach ($parentRow['children'] ?? [] as $childOrder => $childRow) {
                $this->syncCategory($childRow['slug'], $parent->id, $childRow['name'], $childOrder);
            }
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive: categories may be referenced by live
        // quests and freelancer profiles. Manage removals via the admin
        // Category Management console (hide / archive) instead.
    }

    private function syncCategory(string $slug, ?int $parentId, string $name, int $sortOrder): QuestCategory
    {
        $category = QuestCategory::query()->where('slug', $slug)->first();

        if ($category) {
            $category->update([
                'parent_id' => $parentId,
                'name' => $name,
                'sort_order' => $sortOrder,
            ]);

            return $category->refresh();
        }

        return QuestCategory::query()->create([
            'slug' => $slug,
            'parent_id' => $parentId,
            'name' => $name,
            'description' => null,
            'sort_order' => $sortOrder,
            'is_active' => true,
            'status' => 'active',
        ]);
    }
};
