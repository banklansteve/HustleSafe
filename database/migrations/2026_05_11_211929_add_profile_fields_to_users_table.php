<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('last_name')->nullable()->after('first_name');
            $table->string('phone', 32)->nullable()->after('email');
            $table->string('gender', 32)->nullable()->after('phone');
            $table->date('date_of_birth')->nullable()->after('gender');
            $table->string('company_name')->nullable()->after('date_of_birth');
            $table->text('address_line')->nullable()->after('company_name');
            $table->string('local_government')->nullable()->after('address_line');
            $table->string('state')->nullable()->after('local_government');
            $table->string('account_type', 32)->nullable()->after('state');
            $table->string('google_id')->nullable()->unique()->after('account_type');
            $table->string('avatar_url')->nullable()->after('google_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'last_name',
                'phone',
                'gender',
                'date_of_birth',
                'company_name',
                'address_line',
                'local_government',
                'state',
                'account_type',
                'google_id',
                'avatar_url',
            ]);
        });
    }
};
