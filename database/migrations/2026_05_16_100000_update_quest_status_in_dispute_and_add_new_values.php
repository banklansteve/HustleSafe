<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('quests')->where('status', 'disputed')->update(['status' => 'in_dispute']);
    }

    public function down(): void
    {
        DB::table('quests')->where('status', 'in_dispute')->update(['status' => 'disputed']);
    }
};
