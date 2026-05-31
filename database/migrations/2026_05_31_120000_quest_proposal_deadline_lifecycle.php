<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table): void {
            if (! Schema::hasColumn('quests', 'listing_extension_count')) {
                $table->unsignedTinyInteger('listing_extension_count')->default(0)->after('listing_expires_at');
            }
            if (! Schema::hasColumn('quests', 'listing_extended_at')) {
                $table->timestamp('listing_extended_at')->nullable()->after('listing_extension_count');
            }
            if (! Schema::hasColumn('quests', 'listing_extension_reason')) {
                $table->text('listing_extension_reason')->nullable()->after('listing_extended_at');
            }
            if (! Schema::hasColumn('quests', 'listing_expiry_warning_sent_at')) {
                $table->timestamp('listing_expiry_warning_sent_at')->nullable()->after('listing_extension_reason');
            }
            if (! Schema::hasColumn('quests', 'reposted_from_quest_id')) {
                $table->foreignId('reposted_from_quest_id')->nullable()->after('listing_expiry_warning_sent_at')
                    ->constrained('quests')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('quest_listing_extension_logs')) {
            Schema::create('quest_listing_extension_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedSmallInteger('days_added');
                $table->timestamp('previous_expires_at');
                $table->timestamp('new_expires_at');
                $table->text('reason');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quest_listing_extension_logs');

        Schema::table('quests', function (Blueprint $table): void {
            if (Schema::hasColumn('quests', 'reposted_from_quest_id')) {
                $table->dropConstrainedForeignId('reposted_from_quest_id');
            }
            foreach (['listing_expiry_warning_sent_at', 'listing_extension_reason', 'listing_extended_at', 'listing_extension_count'] as $col) {
                if (Schema::hasColumn('quests', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
