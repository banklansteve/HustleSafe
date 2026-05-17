<?php

namespace App\Support\Admin;

use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

final class AdminManagementRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function resources(): array
    {
        return config('admin_management.resources', []);
    }

    /**
     * @return array<string, mixed>
     */
    public static function resource(string $key): array
    {
        $resources = self::resources();
        if (! isset($resources[$key])) {
            throw new InvalidArgumentException("Unknown admin resource [{$key}].");
        }

        return $resources[$key];
    }

    /**
     * @return class-string<Model>
     */
    public static function modelClass(string $key): string
    {
        $class = self::resource($key)['model'] ?? null;
        if (! is_string($class) || ! is_subclass_of($class, Model::class)) {
            throw new InvalidArgumentException("Invalid model for resource [{$key}].");
        }

        return $class;
    }

    /**
     * @return list<string>
     */
    public static function listColumns(string $key): array
    {
        return self::resource($key)['list_columns'] ?? ['id'];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fieldSchema(string $key): array
    {
        return self::resource($key)['fields'] ?? [];
    }

    /**
     * @return list<string>
     */
    public static function createFields(string $key): array
    {
        return self::resource($key)['create_fields'] ?? array_keys(self::fieldSchema($key));
    }

    /**
     * @return list<string>
     */
    public static function editFields(string $key): array
    {
        return self::resource($key)['edit_fields'] ?? self::createFields($key);
    }

    /**
     * @return list<array{key: string, label: string, order: int, items: list<array{key: string, label: string, href: string, indent: bool}>}>
     */
    public static function sidebarNavigation(): array
    {
        $sections = [];

        foreach (self::resources() as $key => $definition) {
            if (! ($definition['sidebar_visible'] ?? true)) {
                continue;
            }

            $sectionKey = (string) ($definition['sidebar_section'] ?? $definition['group'] ?? 'Other');
            if (! isset($sections[$sectionKey])) {
                $sections[$sectionKey] = [
                    'key' => str($sectionKey)->slug('_')->toString(),
                    'label' => $sectionKey,
                    'order' => (int) ($definition['sidebar_order'] ?? 999),
                    'items' => [],
                ];
            }

            $sections[$sectionKey]['order'] = min(
                $sections[$sectionKey]['order'],
                (int) ($definition['sidebar_order'] ?? 999),
            );

            $sections[$sectionKey]['items'][] = [
                'key' => $key,
                'label' => $definition['label'],
                'href' => route('admin.management.index', ['resource' => $key]),
                'indent' => (bool) ($definition['sidebar_indent'] ?? false),
                'sort' => (int) ($definition['sidebar_sort'] ?? 100),
            ];
        }

        $out = array_values($sections);
        usort($out, static fn (array $a, array $b) => $a['order'] <=> $b['order']);
        foreach ($out as &$section) {
            usort($section['items'], static function (array $a, array $b): int {
                $sort = $a['sort'] <=> $b['sort'];
                if ($sort !== 0) {
                    return $sort;
                }

                return strcmp($a['label'], $b['label']);
            });
            foreach ($section['items'] as &$item) {
                unset($item['sort']);
            }
        }

        return $out;
    }

    /**
     * @return list<array{key: string, label: string, resources: list<array<string, mixed>>}>
     */
    public static function groupedForUi(): array
    {
        $groups = [];
        foreach (self::resources() as $key => $definition) {
            if (! ($definition['sidebar_visible'] ?? true)) {
                continue;
            }

            $group = (string) ($definition['sidebar_section'] ?? $definition['group'] ?? 'Other');
            if (! isset($groups[$group])) {
                $groups[$group] = [
                    'label' => $group,
                    'resources' => [],
                ];
            }
            $groups[$group]['resources'][] = [
                'key' => $key,
                'label' => $definition['label'],
                'description' => $definition['description'] ?? '',
                'creatable' => (bool) ($definition['creatable'] ?? false),
            ];
        }

        $out = [];
        foreach ($groups as $label => $group) {
            $out[] = [
                'key' => str($label)->slug('_')->toString(),
                'label' => $group['label'],
                'resources' => $group['resources'],
            ];
        }

        return $out;
    }
}
