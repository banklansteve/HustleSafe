<?php

namespace App\Observers;

use App\Models\Portfolio;
use Illuminate\Support\Facades\Storage;

class PortfolioObserver
{
    public function deleting(Portfolio $portfolio): void
    {
        foreach ($portfolio->files()->get() as $file) {
            Storage::disk('public')->delete($file->path);
        }

        if ($portfolio->cover_path !== null && $portfolio->cover_path !== '') {
            Storage::disk('public')->delete($portfolio->cover_path);
        }
    }
}
