<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('quest_contracts', 'delivery_extension_count')) {
            Schema::table('quest_contracts', function (Blueprint $table): void {
                $table->unsignedSmallInteger('delivery_extension_count')->default(0)->after('amendment_count');
                $table->date('original_agreed_delivery_date')->nullable()->after('agreed_delivery_date');
                $table->timestamp('deadline_clock_paused_at')->nullable()->after('original_agreed_delivery_date');
                $table->unsignedBigInteger('pending_extension_id')->nullable()->after('deadline_clock_paused_at');
            });
        }

        if (! Schema::hasTable('quest_contract_delivery_extensions')) {
            Schema::create('quest_contract_delivery_extensions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('extension_number');
                $table->foreignId('requested_by_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('reason_category', 64);
                $table->text('explanation');
                $table->date('original_delivery_date');
                $table->date('proposed_delivery_date');
                $table->string('status', 32)->default('pending_client')->index();
                $table->text('progress_note')->nullable();
                $table->json('progress_attachments')->nullable();
                $table->unsignedBigInteger('scope_change_message_id')->nullable();
                $table->foreign('scope_change_message_id', 'qcd_ext_scope_msg_fk')
                    ->references('id')->on('quest_conversation_messages')->nullOnDelete();
                $table->timestamp('client_response_deadline_at');
                $table->date('counter_proposed_date')->nullable();
                $table->timestamp('counter_proposed_at')->nullable();
                $table->timestamp('counter_response_deadline_at')->nullable();
                $table->text('decline_reason')->nullable();
                $table->string('resolution', 32)->nullable();
                $table->date('applied_delivery_date')->nullable();
                $table->unsignedBigInteger('quest_contract_amendment_id')->nullable();
                $table->foreign('quest_contract_amendment_id', 'qcd_ext_amendment_fk')
                    ->references('id')->on('quest_contract_amendments')->nullOnDelete();
                $table->foreignId('resolved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->boolean('client_attributed_delay')->default(false);
                $table->boolean('admin_monitoring_flagged')->default(false);
                $table->timestamp('submitted_at');
                $table->timestamps();

                $table->unique(['quest_contract_id', 'extension_number'], 'qcd_ext_contract_num_uq');
            });
        } elseif (! Schema::hasIndex('quest_contract_delivery_extensions', 'qcd_ext_contract_num_uq')) {
            Schema::table('quest_contract_delivery_extensions', function (Blueprint $table): void {
                $table->unique(['quest_contract_id', 'extension_number'], 'qcd_ext_contract_num_uq');
            });
        }

        if (Schema::hasColumn('quest_contracts', 'pending_extension_id') && ! $this->foreignKeyExists('quest_contracts', 'quest_contracts_pending_ext_fk')) {
            Schema::table('quest_contracts', function (Blueprint $table): void {
                $table->foreign('pending_extension_id', 'quest_contracts_pending_ext_fk')
                    ->references('id')
                    ->on('quest_contract_delivery_extensions')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasTable('user_delivery_metrics')) {
            Schema::create('user_delivery_metrics', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('on_time_completed_count')->default(0);
                $table->unsignedSmallInteger('total_completed_count')->default(0);
                $table->decimal('reliability_score', 5, 2)->nullable();
                $table->boolean('low_reliability_flagged')->default(false);
                $table->boolean('extension_pattern_flagged')->default(false);
                $table->timestamp('calculated_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('freelancer_delivery_extension_logs')) {
            Schema::create('freelancer_delivery_extension_logs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('quest_contract_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('delivery_extension_id');
                $table->foreign('delivery_extension_id', 'fdel_ext_log_ext_fk')
                    ->references('id')->on('quest_contract_delivery_extensions')->cascadeOnDelete();
                $table->string('outcome', 32);
                $table->string('reason_category', 64);
                $table->timestamp('logged_at')->useCurrent();
                $table->timestamps();

                $table->index(['user_id', 'logged_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('freelancer_delivery_extension_logs');
        Schema::dropIfExists('user_delivery_metrics');

        if (Schema::hasColumn('quest_contracts', 'pending_extension_id') && $this->foreignKeyExists('quest_contracts', 'quest_contracts_pending_ext_fk')) {
            Schema::table('quest_contracts', function (Blueprint $table): void {
                $table->dropForeign('quest_contracts_pending_ext_fk');
            });
        }

        Schema::dropIfExists('quest_contract_delivery_extensions');

        if (Schema::hasColumn('quest_contracts', 'delivery_extension_count')) {
            Schema::table('quest_contracts', function (Blueprint $table): void {
                $table->dropColumn([
                    'delivery_extension_count',
                    'original_agreed_delivery_date',
                    'deadline_clock_paused_at',
                    'pending_extension_id',
                ]);
            });
        }
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return count($result) > 0;
    }
};
