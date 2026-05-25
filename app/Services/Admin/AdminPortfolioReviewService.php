<?php

namespace App\Services\Admin;

use App\Enums\PortfolioStatus;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
class AdminPortfolioReviewService
{
    /**
     * @return array<string, mixed>
     */
    public function indexPayload(Request $request): array
    {
        $perPage = min(50, max(10, (int) $request->input('per_page', 20)));
        $sort = (string) $request->input('sort', 'updated_at');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $q = trim((string) $request->input('q', ''));
        $status = trim((string) $request->input('status', ''));
        $visibility = trim((string) $request->input('visibility', ''));

        $allowedSorts = ['updated_at', 'published_at', 'title', 'status', 'id'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'updated_at';
        }

        $query = Portfolio::query()
            ->with(['user:id,name,email,slug,first_name'])
            ->withCount('files');

        if ($q !== '') {
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('title', 'like', '%'.$q.'%')
                    ->orWhere('slug', 'like', '%'.$q.'%')
                    ->orWhere('description', 'like', '%'.$q.'%')
                    ->orWhereHas('user', function (Builder $uq) use ($q): void {
                        $uq->where('email', 'like', '%'.$q.'%')
                            ->orWhere('name', 'like', '%'.$q.'%')
                            ->orWhere('slug', 'like', '%'.$q.'%');
                    });
            });
        }

        if ($status !== '' && PortfolioStatus::tryFrom($status)) {
            $query->where('status', $status);
        }

        if ($visibility === 'hidden') {
            $query->where('admin_hidden', true);
        } elseif ($visibility === 'visible') {
            $query->where('admin_hidden', false);
        }

        $query->orderBy($sort, $direction);

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query->paginate($perPage)->withQueryString();

        return [
            'portfolios' => $paginator->through(fn (Portfolio $p) => $this->mapListRow($p)),
            'filters' => [
                'q' => $q,
                'status' => $status,
                'visibility' => $visibility,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'statusOptions' => collect(PortfolioStatus::cases())->map(fn (PortfolioStatus $s) => [
                'value' => $s->value,
                'label' => str_replace('_', ' ', ucfirst($s->value)),
            ])->values()->all(),
            'summary' => [
                'total' => Portfolio::query()->count(),
                'pending_review' => Portfolio::query()->where('status', PortfolioStatus::PendingReview)->count(),
                'published' => Portfolio::query()->where('status', PortfolioStatus::Published)->count(),
                'hidden' => Portfolio::query()->where('admin_hidden', true)->count(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showPayload(Portfolio $portfolio): array
    {
        $portfolio->load([
            'user:id,name,email,slug,first_name,avatar_url',
            'category:id,name',
            'subcategory:id,name',
            'quest:id,title,uuid',
            'files' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        return [
            'portfolio' => $this->mapDetail($portfolio),
            'statusOptions' => collect(PortfolioStatus::cases())->map(fn (PortfolioStatus $s) => [
                'value' => $s->value,
                'label' => str_replace('_', ' ', ucfirst($s->value)),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array{status?: string, admin_hidden?: bool, note?: string}  $data
     */
    public function applyReview(Portfolio $portfolio, array $data): Portfolio
    {
        $updates = [];

        if (isset($data['admin_hidden'])) {
            $updates['admin_hidden'] = (bool) $data['admin_hidden'];
        }

        if (! empty($data['status'])) {
            $status = PortfolioStatus::tryFrom((string) $data['status']);
            if ($status) {
                $updates['status'] = $status;
                if ($status === PortfolioStatus::Published) {
                    $updates['admin_hidden'] = false;
                    $updates['published_at'] = $portfolio->published_at ?? now();
                }
                if (in_array($status, [PortfolioStatus::Removed, PortfolioStatus::RevisionRequested], true)) {
                    $updates['admin_hidden'] = true;
                }
            }
        }

        if ($updates !== []) {
            $portfolio->forceFill($updates)->save();
        }

        return $portfolio->fresh([
            'user',
            'category',
            'subcategory',
            'quest',
            'files',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapListRow(Portfolio $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'status' => $p->status->value,
            'admin_hidden' => (bool) $p->admin_hidden,
            'files_count' => (int) ($p->files_count ?? 0),
            'cover_url' => $p->coverUrl(),
            'published_at' => $p->published_at?->timezone('Africa/Lagos')->toIso8601String(),
            'updated_at' => $p->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'owner' => [
                'name' => $p->user?->first_name ?: $p->user?->name,
                'email' => $p->user?->email,
                'slug' => $p->user?->slug,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function mapDetail(Portfolio $p): array
    {
        return [
            'id' => $p->id,
            'slug' => $p->slug,
            'title' => $p->title,
            'description' => $p->description,
            'status' => $p->status->value,
            'admin_hidden' => (bool) $p->admin_hidden,
            'cover_url' => $p->coverUrl(),
            'published_at' => $p->published_at?->timezone('Africa/Lagos')->toIso8601String(),
            'started_at' => $p->started_at?->timezone('Africa/Lagos')->toIso8601String(),
            'completed_at' => $p->completed_at?->timezone('Africa/Lagos')->toIso8601String(),
            'updated_at' => $p->updated_at?->timezone('Africa/Lagos')->toIso8601String(),
            'category' => $p->category?->name,
            'subcategory' => $p->subcategory?->name,
            'owner' => [
                'name' => $p->user?->first_name ?: $p->user?->name,
                'email' => $p->user?->email,
                'slug' => $p->user?->slug,
                'avatar_url' => $p->user?->avatar_url,
            ],
            'quest' => $p->quest ? [
                'title' => $p->quest->title,
                'uuid' => $p->quest->uuid,
            ] : null,
            'files' => $p->files->map(fn (PortfolioFile $f) => [
                'id' => $f->id,
                'url' => $f->url(),
                'mime_type' => $f->mime_type,
                'original_name' => $f->original_name,
                'is_image' => $f->isImage(),
                'is_video' => $f->isVideo(),
            ])->values()->all(),
            'public_url' => $p->isPublished() ? route('portfolio.show', $p) : null,
        ];
    }
}
