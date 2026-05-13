<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('visibility', 24)->default('public')->after('status');
            $table->string('freelancer_location_pref', 24)->nullable()->after('visibility');
            $table->string('availability_need', 24)->nullable()->after('freelancer_location_pref');
            $table->string('project_type', 24)->nullable()->after('availability_need');
            $table->unsignedSmallInteger('estimated_hours')->nullable()->after('project_type');
            $table->string('team_size', 24)->nullable()->after('estimated_hours');
            $table->string('promotion_tier', 24)->default('standard')->after('team_size');
            $table->unsignedSmallInteger('auto_listing_expiry_days')->nullable()->after('promotion_tier');
            $table->timestamp('listing_expires_at')->nullable()->after('auto_listing_expiry_days');
            $table->unsignedSmallInteger('max_offers')->nullable()->after('listing_expires_at');
            $table->string('slug', 160)->nullable()->unique()->after('title');
            $table->unsignedBigInteger('views_count')->default(0)->after('slug');
            $table->unsignedInteger('offers_count')->default(0)->after('views_count');
            $table->unsignedInteger('saves_count')->default(0)->after('offers_count');
            $table->string('traffic_source', 128)->nullable()->after('saves_count');
            $table->json('traffic_utm')->nullable()->after('traffic_source');
            $table->date('estimated_delivery_date')->nullable()->after('scheduled_start_date');
        });

        Schema::create('quest_bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['quest_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_bookmarks');

        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn([
                'visibility',
                'freelancer_location_pref',
                'availability_need',
                'project_type',
                'estimated_hours',
                'team_size',
                'promotion_tier',
                'auto_listing_expiry_days',
                'listing_expires_at',
                'max_offers',
                'slug',
                'views_count',
                'offers_count',
                'saves_count',
                'traffic_source',
                'traffic_utm',
                'estimated_delivery_date',
            ]);
        });
    }
};
