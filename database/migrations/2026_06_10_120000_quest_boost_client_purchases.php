<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_boost_payments')) {
            Schema::create('quest_boost_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('quest_id')->constrained()->cascadeOnDelete();
                $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();
                $table->string('tier', 16);
                $table->unsignedBigInteger('amount_minor');
                $table->string('paystack_reference')->unique();
                $table->string('status', 32)->default('pending');
                $table->unsignedBigInteger('quest_boost_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->index(['quest_id', 'status'], 'qbp_quest_status_idx');
            });
        }

        if (Schema::hasTable('quest_boosts')) {
            if (Schema::hasColumn('quest_boosts', 'granted_by_admin_id')) {
                Schema::table('quest_boosts', function (Blueprint $table): void {
                    $table->dropForeign(['granted_by_admin_id']);
                });
                Schema::table('quest_boosts', function (Blueprint $table): void {
                    $table->unsignedBigInteger('granted_by_admin_id')->nullable()->change();
                    $table->foreign('granted_by_admin_id')->references('id')->on('users')->nullOnDelete();
                });
            }

            Schema::table('quest_boosts', function (Blueprint $table): void {
                if (! Schema::hasColumn('quest_boosts', 'purchased_by_client_id')) {
                    $table->foreignId('purchased_by_client_id')->nullable()->after('granted_by_admin_id')
                        ->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('quest_boosts', 'quest_boost_payment_id')) {
                    $table->unsignedBigInteger('quest_boost_payment_id')->nullable()->after('purchased_by_client_id');
                }
            });
        }

        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table): void {
                if (! Schema::hasColumn('quests', 'boost_upsell_dismissed_at')) {
                    $table->timestamp('boost_upsell_dismissed_at')->nullable()->after('listing_expires_at');
                }
                if (! Schema::hasColumn('quests', 'boost_upsell_email_sent_at')) {
                    $table->timestamp('boost_upsell_email_sent_at')->nullable()->after('boost_upsell_dismissed_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quests')) {
            Schema::table('quests', function (Blueprint $table): void {
                if (Schema::hasColumn('quests', 'boost_upsell_email_sent_at')) {
                    $table->dropColumn('boost_upsell_email_sent_at');
                }
                if (Schema::hasColumn('quests', 'boost_upsell_dismissed_at')) {
                    $table->dropColumn('boost_upsell_dismissed_at');
                }
            });
        }

        if (Schema::hasTable('quest_boosts')) {
            Schema::table('quest_boosts', function (Blueprint $table): void {
                if (Schema::hasColumn('quest_boosts', 'quest_boost_payment_id')) {
                    $table->dropColumn('quest_boost_payment_id');
                }
                if (Schema::hasColumn('quest_boosts', 'purchased_by_client_id')) {
                    $table->dropConstrainedForeignId('purchased_by_client_id');
                }
            });
        }

        Schema::dropIfExists('quest_boost_payments');
    }
};
