<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $users = DB::table('users')->select([
            'id',
            'trust_score',
            'client_trust_score',
            'profile_completion_percent',
            'avg_rating_as_freelancer',
            'avg_rating_as_client',
            'ratings_count_as_freelancer',
            'ratings_count_as_client',
        ])->get();

        foreach ($users as $u) {
            DB::table('user_trust_metrics')->insert([
                'user_id' => $u->id,
                'freelancer_trust_score' => (int) ($u->trust_score ?? 0),
                'client_trust_score' => (int) ($u->client_trust_score ?? 50),
                'profile_completion_percent' => (int) ($u->profile_completion_percent ?? 0),
                'avg_rating_as_freelancer' => $u->avg_rating_as_freelancer,
                'avg_rating_as_client' => $u->avg_rating_as_client,
                'ratings_count_as_freelancer' => (int) ($u->ratings_count_as_freelancer ?? 0),
                'ratings_count_as_client' => (int) ($u->ratings_count_as_client ?? 0),
                'last_recomputed_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'trust_score',
                'client_trust_score',
                'profile_completion_percent',
                'avg_rating_as_freelancer',
                'avg_rating_as_client',
                'ratings_count_as_freelancer',
                'ratings_count_as_client',
                'state',
                'local_government',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('trust_score')->default(0);
            $table->unsignedSmallInteger('client_trust_score')->default(50);
            $table->unsignedTinyInteger('profile_completion_percent')->default(0);
            $table->decimal('avg_rating_as_freelancer', 3, 2)->nullable();
            $table->decimal('avg_rating_as_client', 3, 2)->nullable();
            $table->unsignedInteger('ratings_count_as_freelancer')->default(0);
            $table->unsignedInteger('ratings_count_as_client')->default(0);
            $table->string('local_government')->nullable()->after('address_line');
            $table->string('state')->nullable()->after('local_government');
        });

        $metrics = DB::table('user_trust_metrics')->get();
        foreach ($metrics as $m) {
            DB::table('users')->where('id', $m->user_id)->update([
                'trust_score' => $m->freelancer_trust_score,
                'client_trust_score' => $m->client_trust_score,
                'profile_completion_percent' => $m->profile_completion_percent,
                'avg_rating_as_freelancer' => $m->avg_rating_as_freelancer,
                'avg_rating_as_client' => $m->avg_rating_as_client,
                'ratings_count_as_freelancer' => $m->ratings_count_as_freelancer,
                'ratings_count_as_client' => $m->ratings_count_as_client,
            ]);
        }
    }
};
