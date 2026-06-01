<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table) {
                $columns = [
                    'visibility' => fn () => $table->string('visibility', 24)->default('public'),
                    'freelancer_location_pref' => fn () => $table->string('freelancer_location_pref', 24)->nullable(),
                    'availability_need' => fn () => $table->string('availability_need', 24)->nullable(),
                    'project_type' => fn () => $table->string('project_type', 24)->nullable(),
                    'estimated_hours' => fn () => $table->unsignedSmallInteger('estimated_hours')->nullable(),
                    'team_size' => fn () => $table->string('team_size', 24)->nullable(),
                    'promotion_tier' => fn () => $table->string('promotion_tier', 24)->default('standard'),
                    'auto_listing_expiry_days' => fn () => $table->unsignedSmallInteger('auto_listing_expiry_days')->nullable(),
                    'listing_expires_at' => fn () => $table->timestamp('listing_expires_at')->nullable(),
                    'max_offers' => fn () => $table->unsignedSmallInteger('max_offers')->nullable(),
                    'slug' => fn () => $table->string('slug', 160)->nullable()->unique(),
                    'views_count' => fn () => $table->unsignedBigInteger('views_count')->default(0),
                    'offers_count' => fn () => $table->unsignedInteger('offers_count')->default(0),
                    'saves_count' => fn () => $table->unsignedInteger('saves_count')->default(0),
                    'traffic_source' => fn () => $table->string('traffic_source', 128)->nullable(),
                    'traffic_utm' => fn () => $table->json('traffic_utm')->nullable(),
                    'estimated_delivery_date' => fn () => $table->date('estimated_delivery_date')->nullable(),
                ];

                foreach ($columns as $name => $add) {
                    if (! Schema::hasColumn('quests', $name)) {
                        $add();
                    }
                }
            });
        }

        if (! Schema::hasTable('quest_bookmarks')) {
            Schema::create('quest_bookmarks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['quest_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_bookmarks');

        if (! Schema::hasTable('quests')) {
            return;
        }

        Schema::table('quests', function (Blueprint $table) {
            $columns = array_filter([
                'visibility', 'freelancer_location_pref', 'availability_need', 'project_type',
                'estimated_hours', 'team_size', 'promotion_tier', 'auto_listing_expiry_days',
                'listing_expires_at', 'max_offers', 'slug', 'views_count', 'offers_count',
                'saves_count', 'traffic_source', 'traffic_utm', 'estimated_delivery_date',
            ], fn (string $col) => Schema::hasColumn('quests', $col));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
