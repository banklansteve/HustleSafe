<?php

namespace App\Services\Admin\ContractManagement;

use App\Models\ContractSavedFilter;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class ContractManagementSavedFilterService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        return ContractSavedFilter::query()
            ->where('user_id', $user->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->map(fn (ContractSavedFilter $filter) => $this->row($filter))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function save(User $user, string $name, array $filters, bool $isDefault = false): array
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => __('Filter name is required.')]);
        }

        if ($isDefault) {
            ContractSavedFilter::query()
                ->where('user_id', $user->id)
                ->update(['is_default' => false]);
        }

        $filter = ContractSavedFilter::query()->updateOrCreate(
            ['user_id' => $user->id, 'name' => $name],
            ['filters' => $filters, 'is_default' => $isDefault],
        );

        return $this->row($filter->fresh());
    }

    public function delete(User $user, ContractSavedFilter $filter): void
    {
        if ((int) $filter->user_id !== (int) $user->id) {
            abort(403);
        }

        $filter->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function row(ContractSavedFilter $filter): array
    {
        return [
            'id' => $filter->id,
            'name' => $filter->name,
            'filters' => $filter->filters ?? [],
            'is_default' => (bool) $filter->is_default,
            'updated_at' => $filter->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
        ];
    }
}
