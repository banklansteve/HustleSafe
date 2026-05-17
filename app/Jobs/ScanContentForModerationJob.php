<?php

namespace App\Jobs;

use App\Services\Moderation\ContentModerationScannerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanContentForModerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $modelClass,
        public readonly int $modelId,
    ) {}

    public function handle(ContentModerationScannerService $scanner): void
    {
        if (! is_a($this->modelClass, Model::class, true)) {
            return;
        }

        $model = $this->modelClass::query()->find($this->modelId);
        if ($model instanceof Model) {
            $scanner->scan($model);
        }
    }
}
