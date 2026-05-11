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
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 64)->nullable()->unique()->after('id');
            $table->string('slug', 128)->nullable()->unique()->after('username');
            $table->string('uid', 8)->nullable()->unique()->after('slug');
            $table->foreignId('role_id')->nullable()->after('account_type')->constrained('roles')->restrictOnDelete();
            $table->string('profession')->nullable()->after('role_id');
            $table->text('bio')->nullable()->after('profession');
            $table->string('headline')->nullable()->after('bio');
            $table->decimal('hourly_rate_min', 12, 2)->nullable()->after('headline');
            $table->decimal('hourly_rate_max', 12, 2)->nullable()->after('hourly_rate_min');
            $table->unsignedTinyInteger('years_experience')->nullable()->after('hourly_rate_max');
            $table->string('availability', 32)->nullable()->after('years_experience');
            $table->string('verification_tier', 32)->nullable()->after('availability');
            $table->unsignedSmallInteger('trust_score')->default(0)->after('verification_tier');
            $table->unsignedSmallInteger('response_time_hours')->nullable()->after('trust_score');
            $table->string('job_title')->nullable()->after('response_time_hours');
            $table->string('company_size', 32)->nullable()->after('job_title');
            $table->string('timezone', 64)->nullable()->default('Africa/Lagos')->after('company_size');
            $table->string('locale', 16)->nullable()->default('en')->after('timezone');
            $table->unsignedTinyInteger('onboarding_step')->default(0)->after('locale');
            $table->timestamp('last_active_at')->nullable()->after('onboarding_step');
            $table->timestamp('suspended_at')->nullable()->after('last_active_at');
        });

        $clientRoleId = DB::table('roles')->where('slug', 'client')->value('id');
        $freelancerRoleId = DB::table('roles')->where('slug', 'freelancer')->value('id');

        $users = DB::table('users')->select('id', 'name', 'email', 'account_type')->get();
        foreach ($users as $user) {
            $baseName = $user->name ?: 'user';
            $slugBase = Str::slug($baseName) ?: 'user';
            $slug = $slugBase;
            $n = 0;
            while (DB::table('users')->where('slug', $slug)->where('id', '!=', $user->id)->exists()) {
                $slug = $slugBase.'-'.Str::lower(Str::random(4));
                $n++;
                if ($n > 50) {
                    $slug = $slugBase.'-'.$user->id;
                    break;
                }
            }

            $emailLocal = explode('@', (string) $user->email)[0];
            $username = Str::slug($emailLocal, '');
            $username = $username !== '' ? substr($username, 0, 60) : 'user'.$user->id;
            $u = $username;
            $i = 0;
            while (DB::table('users')->where('username', $u)->where('id', '!=', $user->id)->exists()) {
                $u = substr($username, 0, 50).$i;
                $i++;
            }

            $roleId = match ($user->account_type ?? null) {
                'hustler' => $freelancerRoleId,
                'sponsor' => $clientRoleId,
                default => $clientRoleId,
            };

            $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            do {
                $uid = '';
                for ($j = 0; $j < 8; $j++) {
                    $uid .= $chars[random_int(0, strlen($chars) - 1)];
                }
            } while (DB::table('users')->where('uid', $uid)->exists());

            DB::table('users')->where('id', $user->id)->update([
                'username' => $u,
                'slug' => $slug,
                'uid' => $uid,
                'role_id' => $roleId,
                'updated_at' => now(),
            ]);
        }

    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn([
                'username',
                'slug',
                'uid',
                'role_id',
                'profession',
                'bio',
                'headline',
                'hourly_rate_min',
                'hourly_rate_max',
                'years_experience',
                'availability',
                'verification_tier',
                'trust_score',
                'response_time_hours',
                'job_title',
                'company_size',
                'timezone',
                'locale',
                'onboarding_step',
                'last_active_at',
                'suspended_at',
            ]);
        });
    }
};
