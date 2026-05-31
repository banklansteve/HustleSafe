<?php

namespace App\Services\Operations;

use App\Models\StaffProactiveOutreachItem;
use App\Models\StaffResponseTemplate;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StaffResponseTemplateService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function listing(?string $situationKey = null, bool $activeOnly = true): array
    {
        $query = StaffResponseTemplate::query()->orderBy('sort_order')->orderBy('title');

        if ($activeOnly) {
            $query->where('is_active', true);
        }

        if ($situationKey) {
            $query->where('situation_key', $situationKey);
        }

        return $query->get()->map(fn (StaffResponseTemplate $template) => $this->row($template))->all();
    }

    public function findBySlug(string $slug): ?StaffResponseTemplate
    {
        return StaffResponseTemplate::query()->where('slug', $slug)->first();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $admin, array $data): StaffResponseTemplate
    {
        $slug = $data['slug'] ?? Str::slug($data['title']);

        return StaffResponseTemplate::query()->create([
            'slug' => $this->uniqueSlug($slug),
            'situation_key' => $data['situation_key'],
            'category' => $data['category'],
            'title' => $data['title'],
            'subject' => $data['subject'],
            'body' => $data['body'],
            'policy_tags' => $data['policy_tags'] ?? [],
            'placeholders' => $data['placeholders'] ?? $this->extractPlaceholders($data['body']),
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 100,
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(StaffResponseTemplate $template, User $admin, array $data): StaffResponseTemplate
    {
        if (isset($data['slug']) && $data['slug'] !== $template->slug) {
            $data['slug'] = $this->uniqueSlug($data['slug'], $template->id);
        }

        if (isset($data['body'])) {
            $data['placeholders'] = $data['placeholders'] ?? $this->extractPlaceholders($data['body']);
        }

        $data['updated_by'] = $admin->id;
        $template->fill($data)->save();

        return $template->fresh();
    }

    /**
     * @param  array<string, string|null>  $replacements
     * @return array{subject: string, body: string}
     */
    public function render(StaffResponseTemplate $template, array $replacements): array
    {
        return [
            'subject' => $this->applyPlaceholders($template->subject, $replacements),
            'body' => $this->applyPlaceholders($template->body, $replacements),
        ];
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function renderForItem(StaffResponseTemplate $template, StaffProactiveOutreachItem $item): array
    {
        $item->loadMissing(['targetUser', 'quest', 'offer', 'dispute']);

        return $this->render($template, $this->defaultReplacements($item));
    }

    /**
     * @return array<string, string>
     */
    public function defaultReplacements(StaffProactiveOutreachItem $item): array
    {
        $context = $item->context ?? [];
        $user = $item->targetUser;

        return [
            'name' => $user?->first_name ?: $user?->name ?: 'there',
            'first_name' => $user?->first_name ?: Str::before($user?->name ?? 'there', ' ') ?: 'there',
            'email' => $user?->email ?? '',
            'quest_title' => $item->quest?->title ?? ($context['quest_title'] ?? 'your quest'),
            'quest_reference' => $item->quest?->reference_code ?? ($context['quest_reference'] ?? ''),
            'freelancer_name' => $context['freelancer_name'] ?? ($item->offer?->freelancer?->name ?? ''),
            'client_name' => $context['client_name'] ?? ($item->quest?->client?->name ?? ''),
            'days_inactive' => (string) ($context['days_inactive'] ?? ''),
            'rating_before' => (string) ($context['rating_before'] ?? ''),
            'rating_after' => (string) ($context['rating_after'] ?? ''),
            'staff_name' => auth()->user()?->name ?? 'HustleSafe Support',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function row(StaffResponseTemplate $template): array
    {
        return [
            'id' => $template->id,
            'slug' => $template->slug,
            'situation_key' => $template->situation_key,
            'situation_label' => config("operations.proactive_outreach.situations.{$template->situation_key}.label"),
            'category' => $template->category,
            'title' => $template->title,
            'subject' => $template->subject,
            'body' => $template->body,
            'policy_tags' => $template->policy_tags ?? [],
            'placeholders' => $template->placeholders ?? [],
            'is_active' => $template->is_active,
            'sort_order' => $template->sort_order,
            'updated_at' => $template->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    public function situationOptions(): array
    {
        return collect(config('operations.proactive_outreach.situations', []))
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta['label'] ?? Str::headline(str_replace('_', ' ', $key)),
                'category' => $meta['category'] ?? 'general',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, string|null>  $replacements
     */
    private function applyPlaceholders(string $text, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $text = str_replace(':'.$key, (string) ($value ?? ''), $text);
        }

        return $text;
    }

    /**
     * @return array<int, string>
     */
    private function extractPlaceholders(string $body): array
    {
        preg_match_all('/:([a-z_]+)/', $body, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function uniqueSlug(string $slug, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug) ?: 'template';
        $candidate = $base;
        $suffix = 2;

        while (StaffResponseTemplate::query()
            ->when($ignoreId, fn ($q) => $q->where('id', '<>', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
