<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_risk_profiles')) {
            Schema::create('user_risk_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedTinyInteger('composite_score')->default(0)->index();
                $table->string('tier', 16)->default('low')->index();
                $table->json('breakdown')->nullable();
                $table->json('signals')->nullable();
                $table->boolean('in_risk_queue')->default(false)->index();
                $table->timestamp('queued_at')->nullable();
                $table->timestamp('calculated_at')->nullable();
                $table->unsignedTinyInteger('previous_score')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('user_risk_network_notes')) {
            Schema::create('user_risk_network_notes', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('subject_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
                $table->text('note');
                $table->timestamps();

                $table->index(['subject_user_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('staff_watchlist_feed_events')) {
            Schema::create('staff_watchlist_feed_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('watched_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('staff_watchlist_item_id')->nullable()->constrained('staff_watchlist_items')->nullOnDelete();
                $table->string('event_type', 48)->index();
                $table->string('severity', 16)->default('observe')->index();
                $table->string('title');
                $table->text('summary')->nullable();
                $table->string('entity_type', 120)->nullable();
                $table->unsignedBigInteger('entity_id')->nullable();
                $table->string('action_url', 500)->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('occurred_at')->index();
                $table->timestamps();

                $table->index(['watched_user_id', 'occurred_at']);
            });
        }

        if (Schema::hasTable('staff_watchlist_items')) {
            Schema::table('staff_watchlist_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('staff_watchlist_items', 'visibility')) {
                    $table->string('visibility', 16)->default('personal')->index()->after('staff_user_id');
                }
                if (! Schema::hasColumn('staff_watchlist_items', 'reason')) {
                    $table->string('reason', 300)->nullable()->after('label');
                }
                if (! Schema::hasColumn('staff_watchlist_items', 'review_by_date')) {
                    $table->date('review_by_date')->nullable()->index()->after('reason');
                }
                if (! Schema::hasColumn('staff_watchlist_items', 'severity')) {
                    $table->string('severity', 16)->default('observe')->index()->after('review_by_date');
                }
            });

            try {
                Schema::table('staff_watchlist_items', function (Blueprint $table): void {
                    $table->dropUnique('staff_watchlist_unique');
                });
            } catch (\Throwable) {
                // already dropped or different name
            }

            try {
                Schema::table('staff_watchlist_items', function (Blueprint $table): void {
                    $table->unique(
                        ['staff_user_id', 'watchable_type', 'watchable_id', 'visibility'],
                        'staff_watchlist_user_unique',
                    );
                });
            } catch (\Throwable) {
                // unique may already exist
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_watchlist_feed_events');
        Schema::dropIfExists('user_risk_network_notes');
        Schema::dropIfExists('user_risk_profiles');

        if (Schema::hasTable('staff_watchlist_items')) {
            Schema::table('staff_watchlist_items', function (Blueprint $table): void {
                foreach (['visibility', 'reason', 'review_by_date', 'severity'] as $col) {
                    if (Schema::hasColumn('staff_watchlist_items', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
