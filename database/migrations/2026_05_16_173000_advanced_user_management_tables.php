<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'nin')) {
                $table->string('nin', 32)->nullable()->after('phone')->index();
            }
            if (! Schema::hasColumn('users', 'bvn')) {
                $table->string('bvn', 32)->nullable()->after('nin')->index();
            }
            if (! Schema::hasColumn('users', 'under_review_at')) {
                $table->timestamp('under_review_at')->nullable()->after('suspended_at')->index();
            }
            if (! Schema::hasColumn('users', 'banned_at')) {
                $table->timestamp('banned_at')->nullable()->after('under_review_at')->index();
            }
            if (! Schema::hasColumn('users', 'ban_reason')) {
                $table->text('ban_reason')->nullable()->after('banned_at');
            }
        });

        Schema::create('admin_user_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->json('context')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_user_tags', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('color', 32)->default('teal');
            $table->timestamps();
        });

        Schema::create('admin_user_tag_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_tag_id')->constrained('admin_user_tags')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'admin_user_tag_id']);
        });

        Schema::create('admin_user_segments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->timestamps();
        });

        Schema::create('admin_user_sanctions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('reason_code', 80)->index();
            $table->text('notes')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('admin_user_badges', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('admin_user_badge_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admin_user_badge_id')->constrained('admin_user_badges')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'admin_user_badge_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_user_badge_user');
        Schema::dropIfExists('admin_user_badges');
        Schema::dropIfExists('admin_user_sanctions');
        Schema::dropIfExists('admin_user_segments');
        Schema::dropIfExists('admin_user_tag_user');
        Schema::dropIfExists('admin_user_tags');
        Schema::dropIfExists('admin_user_notes');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['ban_reason', 'banned_at', 'under_review_at', 'bvn', 'nin'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
