<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            if (! Schema::hasColumn('conversation_message_flags', 'detection_reasoning')) {
                $table->json('detection_reasoning')->nullable()->after('confidence');
            }
            if (! Schema::hasColumn('conversation_message_flags', 'pattern_score')) {
                $table->unsignedTinyInteger('pattern_score')->nullable()->after('detection_reasoning');
            }
            if (! Schema::hasColumn('conversation_message_flags', 'context_score')) {
                $table->unsignedTinyInteger('context_score')->nullable()->after('pattern_score');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('conversation_message_flags')) {
            return;
        }

        Schema::table('conversation_message_flags', function (Blueprint $table): void {
            foreach (['context_score', 'pattern_score', 'detection_reasoning'] as $column) {
                if (Schema::hasColumn('conversation_message_flags', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
