<?php

namespace App\Console\Commands;

use App\Models\StaffKnowledgeArticle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SeedStaffKnowledgeBaseCommand extends Command
{
    protected $signature = 'knowledge-base:seed {--force : Update existing articles matched by slug}';

    protected $description = 'Seed or update staff knowledge base articles for the operations console';

    public function handle(): int
    {
        if (! Schema::hasTable('staff_knowledge_articles')) {
            $this->error('Table staff_knowledge_articles does not exist. Run migrations first.');

            return self::FAILURE;
        }

        $articles = require database_path('seeders/data/staff_knowledge_articles.php');
        $created = 0;
        $updated = 0;

        foreach ($articles as $row) {
            $existing = StaffKnowledgeArticle::query()->where('slug', $row['slug'])->first();

            if ($existing && ! $this->option('force')) {
                $this->line("Skipped (exists): {$row['slug']}");

                continue;
            }

            StaffKnowledgeArticle::query()->updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'category' => $row['category'],
                    'body' => $row['body'],
                    'status' => $row['status'] ?? 'published',
                ],
            );

            $existing ? $updated++ : $created++;
            $this->info(($existing ? 'Updated' : 'Created').": {$row['title']}");
        }

        $this->newLine();
        $this->info("Done. Created: {$created}, updated: {$updated}.");

        return self::SUCCESS;
    }
}
