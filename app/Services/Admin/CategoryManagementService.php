<?php

namespace App\Services\Admin;

use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Models\QuestCategory;
use App\Support\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CategoryManagementService
{
    public const CACHE_KEY_TREE = 'quest_categories.tree.active';

    public function overview(): array
    {
        $parents = QuestCategory::query()->parents()->with(['children', 'children.freelancers'])->orderBy('sort_order')->orderBy('name')->get();
        $openCounts = $this->openQuestCountsByCategory();
        $freelancerCounts = $this->freelancerCountsByCategory();

        $tree = $parents->map(fn (QuestCategory $parent) => $this->parentPayload($parent, $openCounts, $freelancerCounts))->values();
        $activeParents = $parents->where('status', 'active');
        $activeSubcategories = $parents->flatMap->children->where('status', 'active');
        $mostActive = $tree->sortByDesc('active_quests_count')->first();

        return [
            'summary' => [
                'active_categories' => $activeParents->count(),
                'active_subcategories' => $activeSubcategories->count(),
                'open_quests' => array_sum($openCounts),
                'most_active_category' => $mostActive ? [
                    'name' => $mostActive['name'],
                    'open_quests' => $mostActive['active_quests_count'],
                ] : null,
            ],
            'tree' => $tree,
            'archived' => QuestCategory::query()
                ->where('status', 'archived')
                ->with('parent:id,name')
                ->orderBy('name')
                ->get()
                ->map(fn (QuestCategory $category) => $this->archivedPayload($category, $openCounts, $freelancerCounts))
                ->values(),
            'defaults' => [
                'client_fee_percent' => PlatformSettings::platformFeePercent(),
                'freelancer_fee_percent' => (float) config('escrow.freelancer_fee_percent', 10),
            ],
            'icons' => $this->iconLibrary(),
        ];
    }

    public function save(array $data, int $adminId, ?QuestCategory $category = null): QuestCategory
    {
        $this->assertUniqueWithinParent($data['name'], $data['slug'], $data['parent_id'] ?? null, $category?->id);
        $this->assertEditAcknowledgements($data, $category);

        $payload = $this->normalisePayload($data, $adminId, $category);
        $category
            ? $category->update($payload)
            : $category = QuestCategory::query()->create($payload);

        $this->invalidateCaches();

        return $category->fresh(['parent']);
    }

    public function hide(QuestCategory $category): QuestCategory
    {
        $category->update(['previous_status' => $category->status, 'status' => 'hidden']);
        if ($category->parent_id === null) {
            $category->children()->where('status', 'active')->update(['previous_status' => 'active', 'status' => 'hidden', 'is_active' => false]);
        }
        $this->invalidateCaches();

        return $category->fresh();
    }

    public function archive(QuestCategory $category): QuestCategory
    {
        $open = $this->openQuestCount($category);
        if ($open > 0) {
            throw ValidationException::withMessages([
                'category' => "This category has {$open} open Quests that would no longer have a valid category. Please reassign these Quests before archiving.",
            ]);
        }

        $activeChildren = $category->parent_id === null ? $category->children()->where('status', 'active')->pluck('name')->all() : [];
        $category->update(['previous_status' => $category->status, 'status' => 'archived']);
        if ($category->parent_id === null) {
            $category->children()->where('status', '<>', 'archived')->update(['previous_status' => DB::raw('status'), 'status' => 'archived', 'is_active' => false, 'archived_at' => now()]);
        }
        $this->invalidateCaches();

        return $category->fresh()->setAttribute('archived_children', $activeChildren);
    }

    public function restore(QuestCategory $category): QuestCategory
    {
        $category->update(['status' => $category->previous_status ?: 'active', 'previous_status' => null]);
        $this->invalidateCaches();

        return $category->fresh();
    }

    public function reorder(array $items, bool $confirmMove = false): array
    {
        $ids = collect($items)->pluck('id')->all();
        $before = QuestCategory::query()->whereIn('id', $ids)->get(['id', 'parent_id', 'sort_order'])->map->only(['id', 'parent_id', 'sort_order'])->values()->all();
        $moveImpacts = [];

        foreach ($items as $item) {
            $category = QuestCategory::query()->findOrFail($item['id']);
            $newParent = $item['parent_id'] ?? null;
            if ((int) ($category->parent_id ?? 0) !== (int) ($newParent ?? 0)) {
                if (! $category->isLeaf()) {
                    throw ValidationException::withMessages(['items' => 'Parent categories cannot be dropped inside another parent.']);
                }
                $impact = $this->moveImpact($category, $newParent);
                if (! $confirmMove) {
                    throw ValidationException::withMessages(['move_confirmation' => json_encode($impact)]);
                }
                $moveImpacts[] = $impact;
            }
        }

        DB::transaction(function () use ($items): void {
            foreach ($items as $item) {
                QuestCategory::query()
                    ->whereKey($item['id'])
                    ->update([
                        'parent_id' => $item['parent_id'] ?? null,
                        'sort_order' => $item['sort_order'],
                        'updated_at' => now(),
                    ]);
            }
        });

        $token = (string) Str::uuid();
        Cache::put("category_reorder_undo:{$token}", $before, now()->addSeconds(10));
        $this->invalidateCaches();

        return ['undo_token' => $token, 'move_impacts' => $moveImpacts];
    }

    public function undoReorder(string $token): void
    {
        $snapshot = Cache::pull("category_reorder_undo:{$token}");
        if (! is_array($snapshot)) {
            throw ValidationException::withMessages(['undo' => 'This undo window has expired.']);
        }

        foreach ($snapshot as $item) {
            QuestCategory::query()->whereKey($item['id'])->update([
                'parent_id' => $item['parent_id'],
                'sort_order' => $item['sort_order'],
            ]);
        }
        $this->invalidateCaches();
    }

    public function performance(QuestCategory $category, int $days = 30): array
    {
        $days = in_array($days, [30, 90, 365], true) ? $days : 30;
        $categoryIds = $this->categoryIdsForScope($category);
        $quests = Quest::query()->whereIn('quest_category_id', $categoryIds)->where('created_at', '>=', now()->subDays($days));
        $total = (clone $quests)->count();
        $hired = (clone $quests)->whereNotNull('freelancer_id')->count();
        $proposalAvg = (float) (clone $quests)->avg('offers_count');
        $budgetAvg = (int) (clone $quests)->avg('budget_amount_minor');
        $disputes = (clone $quests)->where('dispute_opened', true)->count();
        $revenue = (clone $quests)->sum(DB::raw('coalesce(paid_out_minor, 0)'));
        $trend = (clone $quests)->selectRaw('date_format(created_at, "%Y-%m") as month, count(*) as total')->groupBy('month')->orderBy('month')->pluck('total', 'month');
        $topChildren = $category->parent_id === null
            ? $category->children()->withCount(['quests as period_quests_count' => fn ($q) => $q->where('created_at', '>=', now()->subDays($days))])->orderByDesc('period_quests_count')->limit(5)->get()
            : collect();
        $open = $this->openQuestCount($category);
        $supply = $category->isLeaf() ? $category->freelancers()->count() : $category->children()->withCount('freelancers')->get()->sum('freelancers_count');

        return [
            'id' => $category->id,
            'name' => $category->name,
            'range_days' => $days,
            'total_quests' => $total,
            'fill_rate' => $total > 0 ? round(($hired / $total) * 100, 1) : 0,
            'average_budget' => $this->money($budgetAvg),
            'average_proposals' => round($proposalAvg, 1),
            'average_time_to_hire_hours' => $this->averageTimeToHireHours($categoryIds, $days),
            'platform_revenue' => $this->money((int) $revenue),
            'dispute_rate' => $total > 0 ? round(($disputes / $total) * 100, 1) : 0,
            'average_rating' => null,
            'trend' => $trend,
            'top_subcategories' => $topChildren->map(fn ($child) => ['name' => $child->name, 'value' => $child->period_quests_count])->values(),
            'supply' => $supply,
            'demand' => $open,
            'supply_demand_label' => $this->supplyDemandLabel($open, $supply),
        ];
    }

    public function bulk(array $data): array
    {
        $categories = QuestCategory::query()->whereIn('id', $data['ids'])->get();
        $updated = 0;

        foreach ($categories as $category) {
            if ($data['action'] === 'status') {
                if ($data['status'] === 'archived' && $this->openQuestCount($category) > 0) {
                    continue;
                }
                $category->update(['status' => $data['status']]);
            } elseif ($data['action'] === 'parent' && $category->isLeaf()) {
                $category->update(['parent_id' => $data['parent_id']]);
            } elseif ($data['action'] === 'fees') {
                $category->update([
                    'uses_fee_override' => (bool) ($data['uses_fee_override'] ?? true),
                    'client_fee_percent' => $data['client_fee_percent'],
                    'freelancer_fee_percent' => $data['freelancer_fee_percent'],
                ]);
            }
            $updated++;
        }

        $this->invalidateCaches();

        return ['requested' => count($data['ids']), 'updated' => $updated];
    }

    public function importPreview(UploadedFile $file, bool $commit = false, int $adminId = 0): array
    {
        $rows = $this->readCsv($file);
        $valid = [];
        $invalid = [];

        foreach ($rows as $index => $row) {
            $errors = $this->validateImportRow($row);
            if ($errors) {
                $invalid[] = ['row' => $index + 2, 'data' => $row, 'errors' => $errors];
            } else {
                $valid[] = ['row' => $index + 2, 'data' => $row];
            }
        }

        $created = ['parents' => 0, 'subcategories' => 0];
        if ($commit) {
            foreach ($valid as $item) {
                $row = $item['data'];
                $parent = QuestCategory::query()->firstOrCreate(
                    ['parent_id' => null, 'name' => $row['parent_category_name']],
                    [
                        'slug' => Str::slug($row['parent_category_name']),
                        'description' => $row['subcategory_name'] ? null : $row['description'],
                        'icon_name' => $row['icon_name'] ?: 'briefcase',
                        'client_fee_percent' => $row['client_fee_percent'] ?: null,
                        'freelancer_fee_percent' => $row['freelancer_fee_percent'] ?: null,
                        'status' => $row['status'] ?: 'active',
                        'created_by' => $adminId,
                        'updated_by' => $adminId,
                    ]
                );
                if ($parent->wasRecentlyCreated) {
                    $created['parents']++;
                }
                if ($row['subcategory_name']) {
                    $child = QuestCategory::query()->firstOrCreate(
                        ['parent_id' => $parent->id, 'name' => $row['subcategory_name']],
                        [
                            'slug' => Str::slug($row['subcategory_name']),
                            'description' => $row['description'],
                            'icon_name' => $row['icon_name'] ?: $parent->icon_name,
                            'client_fee_percent' => $row['client_fee_percent'] ?: null,
                            'freelancer_fee_percent' => $row['freelancer_fee_percent'] ?: null,
                            'status' => $row['status'] ?: 'active',
                            'created_by' => $adminId,
                            'updated_by' => $adminId,
                        ]
                    );
                    if ($child->wasRecentlyCreated) {
                        $created['subcategories']++;
                    }
                }
            }
            $this->invalidateCaches();
        }

        return compact('valid', 'invalid', 'created');
    }

    public function exportSelected(array $ids): StreamedResponse
    {
        $categories = QuestCategory::query()->whereIn('id', $ids)->with('parent')->orderBy('parent_id')->orderBy('sort_order')->get();

        return response()->streamDownload(function () use ($categories): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'parent', 'name', 'slug', 'status', 'active_quests', 'client_fee_percent', 'freelancer_fee_percent']);
            $openCounts = $this->openQuestCountsByCategory();
            foreach ($categories as $category) {
                fputcsv($out, [
                    $category->id,
                    $category->parent?->name,
                    $category->name,
                    $category->slug,
                    $category->status,
                    $openCounts[$category->id] ?? 0,
                    $category->client_fee_percent,
                    $category->freelancer_fee_percent,
                ]);
            }
            fclose($out);
        }, 'category-export.csv');
    }

    public function uniqueCheck(?int $parentId, string $field, string $value, ?int $ignoreId = null): array
    {
        $exists = QuestCategory::query()
            ->where('parent_id', $parentId)
            ->where($field, $value)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();

        return ['unique' => ! $exists];
    }

    public function invalidateCaches(): void
    {
        Cache::forget(self::CACHE_KEY_TREE);
    }

    private function parentPayload(QuestCategory $parent, array $openCounts, array $freelancerCounts): array
    {
        $children = $parent->children->map(fn (QuestCategory $child) => $this->subcategoryPayload($child, $openCounts, $freelancerCounts))->values();
        $activeCount = (int) $children->sum('active_quests_count');

        return $this->basePayload($parent, $openCounts, $freelancerCounts) + [
            'active_quests_count' => $activeCount,
            'subcategories_count' => $children->where('status', '<>', 'archived')->count(),
            'children' => $children,
        ];
    }

    private function subcategoryPayload(QuestCategory $category, array $openCounts, array $freelancerCounts): array
    {
        return $this->basePayload($category, $openCounts, $freelancerCounts) + [
            'active_quests_count' => $openCounts[$category->id] ?? 0,
            'freelancers_count' => $freelancerCounts[$category->id] ?? 0,
        ];
    }

    private function archivedPayload(QuestCategory $category, array $openCounts, array $freelancerCounts): array
    {
        return $this->basePayload($category, $openCounts, $freelancerCounts) + [
            'parent_name' => $category->parent?->name,
            'active_quests_count' => $this->openQuestCount($category),
            'freelancers_count' => $freelancerCounts[$category->id] ?? 0,
        ];
    }

    private function basePayload(QuestCategory $category, array $openCounts, array $freelancerCounts): array
    {
        return [
            'id' => $category->id,
            'parent_id' => $category->parent_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'description' => $category->description,
            'icon_name' => $category->icon_name,
            'icon_color' => $category->icon_color,
            'sort_order' => $category->sort_order,
            'status' => $category->status,
            'is_active' => $category->is_active,
            'uses_fee_override' => $category->uses_fee_override,
            'client_fee_percent' => $category->client_fee_percent,
            'freelancer_fee_percent' => $category->freelancer_fee_percent,
            'budget_guardrails_enabled' => $category->budget_guardrails_enabled,
            'min_budget_minor' => $category->min_budget_minor,
            'max_budget_minor' => $category->max_budget_minor,
            'high_value_approval_enabled' => $category->high_value_approval_enabled,
            'high_value_threshold_minor' => $category->high_value_threshold_minor,
            'impact' => [
                'active_quests' => $this->openQuestCount($category),
                'freelancers' => $category->isLeaf() ? ($freelancerCounts[$category->id] ?? 0) : $category->children->sum(fn ($child) => $freelancerCounts[$child->id] ?? 0),
                'active_contracts' => $this->activeContractCount($category),
            ],
        ];
    }

    private function normalisePayload(array $data, int $adminId, ?QuestCategory $category): array
    {
        $isSubcategory = isset($data['parent_id']) && $data['parent_id'];
        $sort = $data['sort_order'] ?? null;
        if ($sort === null) {
            $sort = (int) QuestCategory::query()->where('parent_id', $data['parent_id'] ?? null)->max('sort_order') + 10;
        }

        return [
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'icon_name' => $data['icon_name'] ?? ($category?->icon_name ?: 'briefcase'),
            'icon_color' => $data['icon_color'] ?? ($category?->icon_color ?: '#0f766e'),
            'sort_order' => $sort,
            'status' => $data['status'],
            'uses_fee_override' => $isSubcategory ? (bool) ($data['uses_fee_override'] ?? false) : true,
            'client_fee_percent' => $data['client_fee_percent'] ?? null,
            'freelancer_fee_percent' => $data['freelancer_fee_percent'] ?? null,
            'budget_guardrails_enabled' => (bool) ($data['budget_guardrails_enabled'] ?? false),
            'min_budget_minor' => $data['budget_guardrails_enabled'] ?? false ? ($data['min_budget_minor'] ?? null) : null,
            'max_budget_minor' => $data['budget_guardrails_enabled'] ?? false ? ($data['max_budget_minor'] ?? null) : null,
            'high_value_approval_enabled' => (bool) ($data['high_value_approval_enabled'] ?? false),
            'high_value_threshold_minor' => $data['high_value_approval_enabled'] ?? false ? ($data['high_value_threshold_minor'] ?? null) : null,
            'created_by' => $category?->created_by ?? $adminId,
            'updated_by' => $adminId,
        ];
    }

    private function assertUniqueWithinParent(string $name, string $slug, ?int $parentId, ?int $ignoreId = null): void
    {
        $query = QuestCategory::query()->where('parent_id', $parentId)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId));
        if ((clone $query)->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'This name is already used in this level.']);
        }
        if ((clone $query)->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'This slug is already used in this level.']);
        }
    }

    private function assertEditAcknowledgements(array $data, ?QuestCategory $category): void
    {
        if (! $category) {
            return;
        }
        if (($data['name'] ?? $category->name) !== $category->name && $this->openQuestCount($category) + $this->freelancerImpactCount($category) > 0 && ! ($data['acknowledge_name_impact'] ?? false)) {
            throw ValidationException::withMessages(['acknowledge_name_impact' => 'Acknowledge the display name impact before saving.']);
        }
        $feeChanged = (string) ($data['client_fee_percent'] ?? '') !== (string) $category->client_fee_percent
            || (string) ($data['freelancer_fee_percent'] ?? '') !== (string) $category->freelancer_fee_percent;
        if ($feeChanged && $this->activeContractCount($category) > 0 && ! ($data['acknowledge_fee_impact'] ?? false)) {
            throw ValidationException::withMessages(['acknowledge_fee_impact' => 'Acknowledge that fee changes only apply to new contracts.']);
        }
    }

    private function openQuestCountsByCategory(): array
    {
        return Quest::query()
            ->where('status', QuestStatus::Open->value)
            ->selectRaw('quest_category_id, count(*) as total')
            ->groupBy('quest_category_id')
            ->pluck('total', 'quest_category_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function freelancerCountsByCategory(): array
    {
        return DB::table('freelancer_quest_category')
            ->selectRaw('quest_category_id, count(distinct user_id) as total')
            ->groupBy('quest_category_id')
            ->pluck('total', 'quest_category_id')
            ->map(fn ($value) => (int) $value)
            ->all();
    }

    private function openQuestCount(QuestCategory $category): int
    {
        return Quest::query()->whereIn('quest_category_id', $this->categoryIdsForScope($category))->where('status', QuestStatus::Open->value)->count();
    }

    private function activeContractCount(QuestCategory $category): int
    {
        return Quest::query()
            ->whereIn('quest_category_id', $this->categoryIdsForScope($category))
            ->whereIn('status', collect(QuestStatus::operationalStatuses())->map->value->all())
            ->where('status', '<>', QuestStatus::Open->value)
            ->count();
    }

    private function freelancerImpactCount(QuestCategory $category): int
    {
        return DB::table('freelancer_quest_category')->whereIn('quest_category_id', $this->categoryIdsForScope($category))->distinct('user_id')->count('user_id');
    }

    private function categoryIdsForScope(QuestCategory $category): array
    {
        if ($category->isLeaf()) {
            return [$category->id];
        }

        return $category->children()->pluck('id')->push($category->id)->all();
    }

    private function moveImpact(QuestCategory $category, ?int $newParentId): array
    {
        return [
            'subcategory_id' => $category->id,
            'subcategory_name' => $category->name,
            'new_parent_id' => $newParentId,
            'active_quests' => $this->openQuestCount($category),
            'freelancers' => $this->freelancerImpactCount($category),
        ];
    }

    private function averageTimeToHireHours(array $categoryIds, int $days): float
    {
        $rows = Quest::query()
            ->whereIn('quest_category_id', $categoryIds)
            ->whereNotNull('freelancer_id')
            ->where('created_at', '>=', now()->subDays($days))
            ->selectRaw('timestampdiff(hour, created_at, coalesce(escrow_funded_at, updated_at)) as hours')
            ->pluck('hours');

        return $rows->count() ? round((float) $rows->avg(), 1) : 0.0;
    }

    private function supplyDemandLabel(int $demand, int $supply): string
    {
        if ($demand > 0 && $demand >= max(1, $supply) * 2) {
            return 'High demand';
        }
        if ($supply > 0 && $supply >= max(1, $demand) * 3) {
            return 'Oversupplied';
        }

        return 'Balanced';
    }

    private function money(int $minor): string
    {
        return '₦'.number_format($minor / 100, 2);
    }

    private function iconLibrary(): array
    {
        return [
            ['group' => 'Technology', 'icons' => ['code', 'device-mobile', 'shield-lock', 'database', 'headset']],
            ['group' => 'Creative', 'icons' => ['palette', 'photo', 'video', 'brush', 'writing']],
            ['group' => 'Trades', 'icons' => ['hammer', 'tools', 'building', 'paint', 'plug']],
            ['group' => 'Business', 'icons' => ['briefcase', 'scale', 'chart-bar', 'cash', 'megaphone']],
        ];
    }

    private function readCsv(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = array_map(fn ($h) => trim((string) $h), fgetcsv($handle) ?: []);
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($headers, array_pad($line, count($headers), '')) ?: [];
        }
        fclose($handle);

        return $rows;
    }

    private function validateImportRow(array $row): array
    {
        $errors = [];
        if (trim((string) ($row['parent_category_name'] ?? '')) === '') {
            $errors[] = 'Parent category name is required.';
        }
        if (! in_array($row['status'] ?? 'active', ['active', 'hidden', 'draft'], true)) {
            $errors[] = 'Status must be active, hidden, or draft.';
        }

        return $errors;
    }
}
