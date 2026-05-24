<?php

use App\Models\KycSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('kyc_settings')) {
            return;
        }

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_level_requirements'],
            ['value' => config('verification_engine.levels')],
        );
    }

    public function down(): void
    {
        // Intentionally left empty — prior DB values may differ per environment.
    }
};
