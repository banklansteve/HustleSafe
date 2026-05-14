<?php

namespace App\Services;

use App\Enums\PortfolioStatus;
use App\Models\Portfolio;
use App\Models\PortfolioFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class PortfolioService
{
    public function __construct(
        protected CloudinaryUploadService $cloudinary,
    ) {}

    public function uniqueSlugFromTitle(string $title, ?int $ignorePortfolioId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')) ?: 'portfolio';
        $candidate = $base;
        $i = 0;

        while (Portfolio::query()
            ->when($ignorePortfolioId !== null, fn ($q) => $q->where('id', '!=', $ignorePortfolioId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.Str::lower(Str::random(4));
            if ($i++ > 50) {
                $candidate = $base.'-'.Str::lower(Str::random(8));
                break;
            }
        }

        return $candidate;
    }

    /**
     * @param  list<UploadedFile>  $uploads
     */
    public function storeUploads(Portfolio $portfolio, array $uploads): void
    {
        $order = (int) $portfolio->files()->max('sort_order');

        foreach ($uploads as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $order++;

            if ($this->cloudinary->isConfigured()) {
                $folder = trim((string) config('cloudinary.folder_portfolios', 'hustleSafe/portfolios'), '/');
                $publicId = 'portfolio_'.$portfolio->id.'_'.Str::uuid()->toString();
                $out = $this->cloudinary->upload($file, $folder, $publicId);

                PortfolioFile::query()->create([
                    'portfolio_id' => $portfolio->id,
                    'disk' => 'cloudinary',
                    'path' => $out['secure_url'],
                    'cloudinary_public_id' => $out['public_id'],
                    'cloudinary_resource_type' => $out['resource_type'],
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'sort_order' => $order,
                ]);
            } else {
                $dir = 'portfolio/'.$portfolio->id;
                $name = Str::uuid()->toString().'.'.$file->getClientOriginalExtension();
                $path = $file->storeAs($dir, $name, 'public');

                PortfolioFile::query()->create([
                    'portfolio_id' => $portfolio->id,
                    'disk' => 'public',
                    'path' => $path,
                    'cloudinary_public_id' => null,
                    'cloudinary_resource_type' => null,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getClientMimeType() ?? 'application/octet-stream',
                    'size_bytes' => $file->getSize() ?: 0,
                    'sort_order' => $order,
                ]);
            }
        }

        $this->ensureCover($portfolio->fresh(['files']));
    }

    /**
     * @param  list<int>  $fileIds
     */
    public function deleteFiles(Portfolio $portfolio, array $fileIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $fileIds)));
        if ($ids === []) {
            return;
        }

        $files = $portfolio->files()->whereIn('id', $ids)->get();
        foreach ($files as $file) {
            $file->purgeFromStorage();
            $file->delete();
        }

        $portfolio->refresh();
        if ($portfolio->files()->doesntExist()) {
            $portfolio->cover_path = null;
            $portfolio->saveQuietly();

            return;
        }

        if ($portfolio->cover_path !== null && ! $portfolio->files()->where('path', $portfolio->cover_path)->exists()) {
            $portfolio->cover_path = null;
            $portfolio->saveQuietly();
        }

        $this->ensureCover($portfolio->fresh(['files']));
    }

    public function ensureCover(?Portfolio $portfolio): void
    {
        if ($portfolio === null) {
            return;
        }

        if ($portfolio->cover_path !== null && $portfolio->files()->where('path', $portfolio->cover_path)->exists()) {
            return;
        }

        $firstImage = $portfolio->files()->get()->first(fn (PortfolioFile $f) => $f->isImage());

        if ($firstImage !== null) {
            $portfolio->cover_path = $firstImage->path;
            $portfolio->saveQuietly();

            return;
        }

        $first = $portfolio->files()->first();
        if ($first !== null) {
            $portfolio->cover_path = $first->path;
            $portfolio->saveQuietly();
        }
    }

    public function applyStatus(Portfolio $portfolio, PortfolioStatus $status): void
    {
        $portfolio->status = $status;
        if ($status === PortfolioStatus::Published) {
            $portfolio->published_at ??= now();
        }
        $portfolio->save();
    }
}
