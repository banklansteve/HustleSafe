<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use App\Support\Admin\AdminDateTimeFormatter;
use App\Support\Admin\AdminManagementRegistry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

final class AdminManagementService
{
    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginate(string $resourceKey, int $perPage = 20, ?string $search = null): LengthAwarePaginator
    {
        $definition = AdminManagementRegistry::resource($resourceKey);
        $modelClass = AdminManagementRegistry::modelClass($resourceKey);

        /** @var Builder<Model> $query */
        $query = $modelClass::query();
        $with = $definition['with'] ?? [];
        if (is_array($with) && $with !== []) {
            $query->with($with);
        }

        if ($search && ! empty($definition['search_columns'])) {
            $query->where(function (Builder $q) use ($definition, $search): void {
                foreach ($definition['search_columns'] as $column) {
                    $q->orWhere($column, 'like', '%'.$search.'%');
                }
            });
        }

        $orderBy = $definition['order_by'] ?? 'id';
        $orderDir = $definition['order_dir'] ?? 'desc';
        $query->orderBy($orderBy, $orderDir);

        return $query
            ->paginate($perPage)
            ->through(fn (Model $model) => $this->serializeRow($resourceKey, $model));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(string $resourceKey, array $payload): Model
    {
        $definition = AdminManagementRegistry::resource($resourceKey);
        if (! ($definition['creatable'] ?? false)) {
            abort(403, 'This resource cannot be created from the admin console.');
        }

        $modelClass = AdminManagementRegistry::modelClass($resourceKey);
        $allowed = AdminManagementRegistry::createFields($resourceKey);
        $data = $this->filterPayload($payload, $allowed, $resourceKey);

        $this->applyBeforeSave($resourceKey, $data, null);

        /** @var Model $model */
        $model = $modelClass::query()->create($data);

        return $model;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(string $resourceKey, int $recordId, array $payload): Model
    {
        $definition = AdminManagementRegistry::resource($resourceKey);
        if (! ($definition['editable'] ?? true)) {
            abort(403, 'This resource cannot be edited.');
        }

        $modelClass = AdminManagementRegistry::modelClass($resourceKey);
        $model = $modelClass::query()->findOrFail($recordId);

        $this->guardProtectedRecord($resourceKey, $model);

        $allowed = AdminManagementRegistry::editFields($resourceKey);
        $data = $this->filterPayload($payload, $allowed, $resourceKey);

        $this->applyBeforeSave($resourceKey, $data, $model);

        $model->fill($data);
        $model->save();

        return $model->fresh();
    }

    public function delete(string $resourceKey, int $recordId): void
    {
        $definition = AdminManagementRegistry::resource($resourceKey);
        if (! ($definition['deletable'] ?? true)) {
            abort(403, 'This resource cannot be deleted.');
        }

        $modelClass = AdminManagementRegistry::modelClass($resourceKey);
        $model = $modelClass::query()->findOrFail($recordId);

        $this->guardProtectedRecord($resourceKey, $model);

        $model->delete();
    }

    public function suspendUser(int $userId, bool $suspend): User
    {
        $user = User::query()->findOrFail($userId);
        $this->guardProtectedRecord('users', $user);

        $user->suspended_at = $suspend ? now() : null;
        $user->save();

        return $user->fresh(['role']);
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeRow(string $resourceKey, Model $model): array
    {
        $definition = AdminManagementRegistry::resource($resourceKey);
        $schema = AdminManagementRegistry::fieldSchema($resourceKey);
        $row = ['id' => $model->getKey(), '_edit' => []];

        $listColumns = AdminManagementRegistry::listColumns($resourceKey);
        $editFields = AdminManagementRegistry::editFields($resourceKey);
        $columns = array_values(array_unique([...$listColumns, ...$editFields]));

        foreach ($columns as $column) {
            if ($column === 'is_suspended') {
                continue;
            }

            $raw = $model->getAttribute($column);
            $fieldSchema = $schema[$column] ?? [];
            $type = $fieldSchema['type'] ?? 'text';

            $row['_edit'][$column] = $this->editValue($raw, $type, $fieldSchema);
            $row[$column] = $this->displayValue($model, $column, $raw, $type, $fieldSchema);
        }

        foreach ($definition['with'] ?? [] as $relation) {
            $relationName = explode(':', $relation)[0];
            $related = $model->relationLoaded($relationName)
                ? $model->getRelation($relationName)
                : null;
            if ($related) {
                $row['_rel_'.$relationName] = $related->toArray();
                $row[$relationName.'_label'] = $this->relatedLabel($related);
            }
        }

        if ($resourceKey === 'users') {
            $row['is_suspended'] = $model->getAttribute('suspended_at') !== null;
            $row['role_slug'] = $model->relationLoaded('role')
                ? ($model->getRelation('role')?->slug ?? '—')
                : '—';
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeDetailRow(string $resourceKey, Model $model): array
    {
        $row = $this->serializeRow($resourceKey, $model);
        $schema = AdminManagementRegistry::fieldSchema($resourceKey);

        foreach ($model->getAttributes() as $column => $raw) {
            if (in_array($column, ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'], true)) {
                continue;
            }

            $fieldSchema = $schema[$column] ?? [];
            $type = $fieldSchema['type'] ?? 'text';
            $row['_edit'][$column] ??= $this->editValue($raw, $type, $fieldSchema);
            $row[$column] = $this->displayValue($model, $column, $raw, $type, $fieldSchema);
        }

        return $row;
    }

    /**
     * @param  list<string>  $allowed
     * @return array<string, mixed>
     */
    private function filterPayload(array $payload, array $allowed, string $resourceKey): array
    {
        $schema = AdminManagementRegistry::fieldSchema($resourceKey);
        $out = [];
        foreach ($allowed as $field) {
            if (! array_key_exists($field, $payload)) {
                continue;
            }
            $value = $payload[$field];
            $type = $schema[$field]['type'] ?? 'text';
            if ($type === 'boolean') {
                $out[$field] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif ($type === 'money_minor') {
                $out[$field] = $value === '' || $value === null
                    ? null
                    : (int) round(((float) str_replace(',', '', (string) $value)) * 100);
            } elseif (in_array($type, ['integer', 'relation'], true)) {
                $out[$field] = $value === '' || $value === null ? null : (int) $value;
            } elseif ($type === 'date') {
                if ($value === '' || $value === null) {
                    $out[$field] = null;
                    continue;
                }
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $value, $m)) {
                    $out[$field] = "{$m[3]}-{$m[2]}-{$m[1]}";
                } else {
                    $out[$field] = $value;
                }
            } elseif (in_array($type, ['json', 'key_value'], true)) {
                if (is_array($value)) {
                    $out[$field] = $type === 'key_value' ? $this->keyValueRowsToArray($value) : $value;
                } elseif (is_string($value)) {
                    $decoded = json_decode($value, true);
                    $out[$field] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
                } else {
                    $out[$field] = $value;
                }
            } else {
                $out[$field] = $value;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyBeforeSave(string $resourceKey, array &$data, ?Model $model): void
    {
        if ($resourceKey === 'users' && $model === null && isset($data['email'])) {
            $data['password'] = bcrypt(Str::random(32));
        }

        if ($resourceKey === 'users' && $model === null) {
            $data['name'] = trim(collect([
                $data['first_name'] ?? null,
                $data['last_name'] ?? null,
            ])->filter(fn ($part) => filled($part))->join(' '));

            $roleSlug = isset($data['role_id']) && $data['role_id']
                ? Role::query()->whereKey($data['role_id'])->value('slug')
                : null;

            if (in_array($roleSlug, ['admin', 'super_admin'], true)) {
                $data['account_type'] = 'admin';
            }

            if (($data['account_type'] ?? null) === 'admin' && empty($data['role_id'])) {
                $data['role_id'] = Role::query()->where('slug', 'admin')->value('id');
            }
        }

        if ($resourceKey === 'users' && isset($data['role_id']) && empty($data['role_id'])) {
            unset($data['role_id']);
        }
    }

    private function guardProtectedRecord(string $resourceKey, Model $model): void
    {
        if ($resourceKey !== 'users' || ! $model instanceof User) {
            return;
        }

        $slug = $model->role?->slug;
        if ($slug === 'super_admin') {
            abort(403, 'Super administrator accounts cannot be modified through this console.');
        }
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function displayValue(Model $model, string $column, mixed $value, string $type, array $schema): mixed
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (($schema['strip_html'] ?? false) && is_string($value)) {
            $value = trim(html_entity_decode(strip_tags($value)));
        }

        if ($type === 'relation') {
            $relation = $schema['relation_name'] ?? str($column)->beforeLast('_id')->toString();
            $related = $model->relationLoaded($relation) ? $model->getRelation($relation) : null;

            if ($related instanceof Model) {
                $label = $this->relatedLabel($related);

                return [
                    'label' => $label,
                    'href' => $related instanceof User ? route('admin.management.users.activity', $related) : null,
                ];
            }
        }

        if ($type === 'money_minor') {
            return $this->formatMoney($value);
        }

        if ($type === 'key_value') {
            return $this->formatKeyValue($value);
        }

        if (AdminDateTimeFormatter::isDateColumn($column)) {
            return AdminDateTimeFormatter::formatValue($value, $column);
        }

        if (is_array($value)) {
            return collect($value)->map(fn ($v, $k) => str($k)->headline().': '.(is_scalar($v) ? $v : json_encode($v)))->implode(', ');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function editValue(mixed $value, string $type, array $schema): mixed
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if (($schema['strip_html'] ?? false) && is_string($value)) {
            return trim(html_entity_decode(strip_tags($value)));
        }

        if ($type === 'money_minor') {
            return $value === null || $value === '' ? '' : number_format(((int) $value) / 100, 2, '.', '');
        }

        if ($type === 'key_value') {
            return collect((array) $value)->map(fn ($v, $k) => [
                'key' => (string) $k,
                'value' => is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE),
            ])->values()->all();
        }

        if ($type === 'json' && is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value;
    }

    private function formatMoney(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return '₦'.number_format(((int) $value) / 100, 2);
    }

    private function formatKeyValue(mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '—';
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        if (! is_array($value)) {
            return (string) $value;
        }

        return collect($value)
            ->map(function ($item, $key): string {
                if (is_array($item)) {
                    $label = is_string($key) ? str($key)->headline()->toString() : (string) ($item['label'] ?? $item['name'] ?? $item['key'] ?? 'Item');
                    $content = collect($item)
                        ->reject(fn ($v, $k) => in_array($k, ['label', 'name', 'key'], true))
                        ->map(fn ($v, $k) => str($k)->headline().': '.(is_scalar($v) ? $v : collect((array) $v)->implode(', ')))
                        ->implode('; ');

                    return trim($label.($content !== '' ? ' — '.$content : ''));
                }

                return str((string) $key)->headline().': '.(is_scalar($item) ? (string) $item : collect((array) $item)->implode(', '));
            })
            ->filter()
            ->implode("\n");
    }

    private function relatedLabel(Model $related): string
    {
        foreach (['name', 'title', 'reference_code', 'email', 'slug'] as $field) {
            $value = $related->getAttribute($field);
            if (is_string($value) && $value !== '') {
                if ($field === 'name' && $related->getAttribute('email')) {
                    return $value.' ('.$related->getAttribute('email').')';
                }

                return $value;
            }
        }

        return '#'.$related->getKey();
    }

    /**
     * @param  array<int, array{key?: mixed, value?: mixed}>  $rows
     * @return array<string, mixed>
     */
    private function keyValueRowsToArray(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $out[$key] = $row['value'] ?? '';
        }

        return $out;
    }
}
