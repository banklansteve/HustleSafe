<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('roles')->where('slug', 'super_admin')->exists();
        if (! $exists) {
            DB::table('roles')->insert([
                'name' => 'Super Administrator',
                'slug' => 'super_admin',
                'description' => 'Full platform control — seed sparingly.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('roles')->where('slug', 'admin')->update([
            'description' => 'Operational admin — limited capabilities (no super-admin tools).',
            'updated_at' => now(),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('client_trust_score')->default(50)->after('trust_score');
            $table->unsignedTinyInteger('profile_completion_percent')->default(0)->after('client_trust_score');
            $table->decimal('avg_rating_as_freelancer', 3, 2)->nullable()->after('profile_completion_percent');
            $table->decimal('avg_rating_as_client', 3, 2)->nullable()->after('avg_rating_as_freelancer');
            $table->unsignedInteger('ratings_count_as_freelancer')->default(0)->after('avg_rating_as_client');
            $table->unsignedInteger('ratings_count_as_client')->default(0)->after('ratings_count_as_freelancer');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'client_trust_score',
                'profile_completion_percent',
                'avg_rating_as_freelancer',
                'avg_rating_as_client',
                'ratings_count_as_freelancer',
                'ratings_count_as_client',
            ]);
        });

        DB::table('roles')->where('slug', 'super_admin')->delete();
    }
};
