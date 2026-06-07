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

        $limits = KycSetting::value('verification_limits');
        if (! is_array($limits)) {
            return;
        }

        if (isset($limits['freelancer_monthly_proposals']) && is_array($limits['freelancer_monthly_proposals'])) {
            return;
        }

        $defaults = config('verification_engine.limits.freelancer_monthly_proposals', []);
        $limits['freelancer_monthly_proposals'] = is_array($defaults) ? $defaults : [];

        KycSetting::query()->updateOrCreate(
            ['key' => 'verification_limits'],
            ['value' => $limits],
        );
    }

    public function down(): void
    {
        // Intentionally left empty.
    }
};
