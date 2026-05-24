<?php

namespace App\Services\Operations;

use App\Models\StaffKnowledgeArticle;
use App\Models\StaffKnowledgeSuggestion;
use App\Models\User;
use App\Services\AdminActivityLogger;
use Illuminate\Support\Str;

class StaffKnowledgeBaseService
{
    public function __construct(private readonly AdminActivityLogger $logger) {}

    public function listing(?string $q = null, ?string $category = null): array
    {
        $query = StaffKnowledgeArticle::query()->where('status', 'published');

        if ($category) {
            $query->where('category', $category);
        }

        if ($q) {
            $query->where(function ($sub) use ($q): void {
                $sub->where('title', 'like', "%{$q}%")
                    ->orWhere('body', 'like', "%{$q}%");
            });
        }

        $articles = $query->orderBy('category')->orderBy('title')->get();

        return [
            'categories' => $articles->pluck('category')->unique()->sort()->values(),
            'articles' => $articles->map(fn (StaffKnowledgeArticle $a) => [
                'id' => $a->id,
                'slug' => $a->slug,
                'title' => $a->title,
                'category' => $a->category,
                'excerpt' => str($a->body)->stripTags()->limit(160)->toString(),
                'updated_at' => $a->updated_at?->toIso8601String(),
            ]),
        ];
    }

    public function article(StaffKnowledgeArticle $article): array
    {
        return [
            'id' => $article->id,
            'slug' => $article->slug,
            'title' => $article->title,
            'category' => $article->category,
            'body' => $article->body,
            'updated_at' => $article->updated_at?->toIso8601String(),
        ];
    }

    public function suggest(User $staff, array $data): StaffKnowledgeSuggestion
    {
        return StaffKnowledgeSuggestion::query()->create([
            'staff_knowledge_article_id' => $data['article_id'] ?? null,
            'suggested_by_staff_id' => $staff->id,
            'body' => $data['body'],
            'status' => 'pending',
        ]);
    }

    public function storeArticle(User $admin, array $data): StaffKnowledgeArticle
    {
        $slug = $data['slug'] ?? Str::slug($data['title']);

        $article = StaffKnowledgeArticle::query()->create([
            'slug' => $slug,
            'title' => $data['title'],
            'category' => $data['category'],
            'body' => $data['body'],
            'status' => $data['status'] ?? 'published',
            'created_by_admin_id' => $admin->id,
            'updated_by_admin_id' => $admin->id,
        ]);

        $this->logger->log($admin, 'staff_knowledge.article_created', StaffKnowledgeArticle::class, $article->id, []);

        return $article;
    }

    public function updateArticle(StaffKnowledgeArticle $article, User $admin, array $data): StaffKnowledgeArticle
    {
        $article->forceFill([
            'title' => $data['title'] ?? $article->title,
            'category' => $data['category'] ?? $article->category,
            'body' => $data['body'] ?? $article->body,
            'status' => $data['status'] ?? $article->status,
            'updated_by_admin_id' => $admin->id,
        ])->save();

        $this->logger->log($admin, 'staff_knowledge.article_updated', StaffKnowledgeArticle::class, $article->id, []);

        return $article;
    }
}
