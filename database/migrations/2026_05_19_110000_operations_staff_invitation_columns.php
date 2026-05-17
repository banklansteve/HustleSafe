<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('operations_staff_invited_at')->nullable();
            $table->foreignId('operations_staff_invited_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('operations_staff_password_set_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('operations_staff_invited_by');
            $table->dropColumn([
                'operations_staff_invited_at',
                'operations_staff_password_set_at',
            ]);
        });
    }
};
