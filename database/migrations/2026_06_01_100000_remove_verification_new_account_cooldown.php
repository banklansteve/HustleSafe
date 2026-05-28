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

        $setting = KycSetting::query()->where('key', 'verification_safeguards')->first();
        if ($setting === null || ! is_array($setting->value)) {
            return;
        }

        if (! array_key_exists('new_account_cooldown_days', $setting->value)) {
            return;
        }

        $value = $setting->value;
        unset($value['new_account_cooldown_days']);
        $setting->forceFill(['value' => $value])->saveQuietly();
    }

    public function down(): void
    {
        // Intentionally not restored — new-account cooldown was removed from the product.
    }
};
