<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $removedSlug = implode('-', ['expert', 'verified']);
        $badgeId = DB::table('promotion_badges')->where('slug', $removedSlug)->value('id');
        if ($badgeId !== null) {
            DB::table('promotion_badge_user')->where('promotion_badge_id', $badgeId)->delete();
            DB::table('promotion_badges')->where('id', $badgeId)->delete();
        }

        $slugs = [
            'top-rated',
            'rising-talent',
            'quest-champion',
            'verified-pro',
            'verified-business',
            'fast-responder',
            'long-term-partner',
        ];

        foreach ($slugs as $index => $slug) {
            DB::table('promotion_badges')->where('slug', $slug)->update(['display_order' => $index + 1]);
        }
    }

    public function down(): void
    {
        //
    }
};
