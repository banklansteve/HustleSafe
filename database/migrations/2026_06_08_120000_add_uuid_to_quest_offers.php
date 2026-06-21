<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('quest_offers')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            if (! Schema::hasColumn('quest_offers', 'uuid')) {
                $table->uuid('uuid')->nullable()->unique()->after('id');
            }
        });

        DB::table('quest_offers')
            ->whereNull('uuid')
            ->orderBy('id')
            ->each(function (object $row): void {
                DB::table('quest_offers')
                    ->where('id', $row->id)
                    ->update(['uuid' => (string) Str::uuid()]);
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('quest_offers') || ! Schema::hasColumn('quest_offers', 'uuid')) {
            return;
        }

        Schema::table('quest_offers', function (Blueprint $table): void {
            $table->dropColumn('uuid');
        });
    }
};
