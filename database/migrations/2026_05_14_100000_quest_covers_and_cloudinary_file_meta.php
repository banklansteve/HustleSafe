<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests') && ! Schema::hasColumn('quests', 'cover_image_url')) {
            Schema::table('quests', function (Blueprint $table) {
                $table->string('cover_image_url', 1024)->nullable();
            });
        }

        if (Schema::hasTable('quest_files')) {
            Schema::table('quest_files', function (Blueprint $table) {
                if (! Schema::hasColumn('quest_files', 'cloudinary_public_id')) {
                    $table->string('cloudinary_public_id', 512)->nullable();
                }
                if (! Schema::hasColumn('quest_files', 'cloudinary_resource_type')) {
                    $table->string('cloudinary_resource_type', 32)->nullable();
                }
            });
        }

        if (Schema::hasTable('portfolio_files')) {
            Schema::table('portfolio_files', function (Blueprint $table) {
                if (! Schema::hasColumn('portfolio_files', 'disk')) {
                    $table->string('disk', 32)->default('public');
                }
                if (! Schema::hasColumn('portfolio_files', 'cloudinary_public_id')) {
                    $table->string('cloudinary_public_id', 512)->nullable();
                }
                if (! Schema::hasColumn('portfolio_files', 'cloudinary_resource_type')) {
                    $table->string('cloudinary_resource_type', 32)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('portfolio_files')) {
            Schema::table('portfolio_files', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('portfolio_files', 'disk') ? 'disk' : null,
                    Schema::hasColumn('portfolio_files', 'cloudinary_public_id') ? 'cloudinary_public_id' : null,
                    Schema::hasColumn('portfolio_files', 'cloudinary_resource_type') ? 'cloudinary_resource_type' : null,
                ]);
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('quest_files')) {
            Schema::table('quest_files', function (Blueprint $table) {
                $columns = array_filter([
                    Schema::hasColumn('quest_files', 'cloudinary_public_id') ? 'cloudinary_public_id' : null,
                    Schema::hasColumn('quest_files', 'cloudinary_resource_type') ? 'cloudinary_resource_type' : null,
                ]);
                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('quests') && Schema::hasColumn('quests', 'cover_image_url')) {
            Schema::table('quests', function (Blueprint $table) {
                $table->dropColumn('cover_image_url');
            });
        }
    }
};
