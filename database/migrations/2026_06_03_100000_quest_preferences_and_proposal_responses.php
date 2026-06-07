<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            if (! Schema::hasColumn('quests', 'delivery_deadline')) {
                $table->date('delivery_deadline')->nullable()->after('estimated_delivery_date');
            }
            if (! Schema::hasColumn('quests', 'preferences_last_updated')) {
                $table->timestamp('preferences_last_updated')->nullable()->after('delivery_deadline');
            }
        });

        if (! Schema::hasTable('quest_preferences')) {
            Schema::create('quest_preferences', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->string('preference_key', 80);
                $table->json('preference_value')->nullable();
                $table->boolean('is_specified')->default(false);
                $table->timestamps();

                $table->unique(['quest_id', 'preference_key']);
            });
        }

        if (! Schema::hasTable('proposal_preference_responses')) {
            Schema::create('proposal_preference_responses', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_offer_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quest_preference_id')->constrained()->cascadeOnDelete();
                $table->string('response_type', 32);
                $table->text('response_text')->nullable();
                $table->timestamps();

                $table->unique(['quest_offer_id', 'quest_preference_id'], 'proposal_pref_response_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_preference_responses');
        Schema::dropIfExists('quest_preferences');

        Schema::table('quests', function (Blueprint $table) {
            if (Schema::hasColumn('quests', 'preferences_last_updated')) {
                $table->dropColumn('preferences_last_updated');
            }
            if (Schema::hasColumn('quests', 'delivery_deadline')) {
                $table->dropColumn('delivery_deadline');
            }
        });
    }
};
