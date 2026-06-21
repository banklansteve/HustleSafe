<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('conversation_policy_warning_replies')) {
            Schema::create('conversation_policy_warning_replies', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('conversation_policy_warning_id');
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('body');
                $table->timestamps();

                $table->foreign('conversation_policy_warning_id', 'policy_warning_replies_warning_fk')
                    ->references('id')
                    ->on('conversation_policy_warnings')
                    ->cascadeOnDelete();

                $table->index(['conversation_policy_warning_id', 'created_at'], 'policy_warning_replies_warning_idx');
            });
        }

        if (Schema::hasTable('conversation_thread_reviews') && ! Schema::hasColumn('conversation_thread_reviews', 'deleted_at')) {
            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_policy_warning_replies');

        if (Schema::hasColumn('conversation_thread_reviews', 'deleted_at')) {
            Schema::table('conversation_thread_reviews', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
