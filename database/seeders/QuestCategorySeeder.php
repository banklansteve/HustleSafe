<?php

namespace Database\Seeders;

use App\Models\QuestCategory;
use Illuminate\Database\Seeder;

class QuestCategorySeeder extends Seeder
{
    public function run(): void
    {
        $tree = require database_path('data/quest_category_tree.php');
        if (! is_array($tree)) {
            return;
        }

        foreach ($tree as $pOrder => $parentRow) {
            $parent = QuestCategory::query()->updateOrCreate(
                ['slug' => $parentRow['slug']],
                [
                    'parent_id' => null,
                    'name' => $parentRow['name'],
                    'description' => null,
                    'sort_order' => $pOrder,
                    'is_active' => true,
                ]
            );

            foreach ($parentRow['children'] ?? [] as $cOrder => $childRow) {
                QuestCategory::query()->updateOrCreate(
                    ['slug' => $childRow['slug']],
                    [
                        'parent_id' => $parent->id,
                        'name' => $childRow['name'],
                        'description' => null,
                        'sort_order' => $cOrder,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
