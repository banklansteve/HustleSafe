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

        $legacy = KycSetting::value('verification_level_requirements');

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_client_level_requirements'],
            ['value' => $legacy ?? config('verification_engine.client_levels')],
        );

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_freelancer_level_requirements'],
            ['value' => config('verification_engine.freelancer_levels')],
        );

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_stage_content'],
            ['value' => config('verification_engine.stage_content')],
        );
    }

    public function down(): void
    {
        // Intentionally left empty.
    }
};
