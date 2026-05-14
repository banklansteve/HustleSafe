<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quests', function (Blueprint $table) {
            $table->string('cover_image_url', 1024)->nullable()->after('description');
        });

        Schema::table('quest_files', function (Blueprint $table) {
            $table->string('cloudinary_public_id', 512)->nullable()->after('path');
            $table->string('cloudinary_resource_type', 32)->nullable()->after('cloudinary_public_id');
        });

        Schema::table('portfolio_files', function (Blueprint $table) {
            $table->string('disk', 32)->default('public')->after('portfolio_id');
            $table->string('cloudinary_public_id', 512)->nullable()->after('path');
            $table->string('cloudinary_resource_type', 32)->nullable()->after('cloudinary_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_files', function (Blueprint $table) {
            $table->dropColumn(['disk', 'cloudinary_public_id', 'cloudinary_resource_type']);
        });

        Schema::table('quest_files', function (Blueprint $table) {
            $table->dropColumn(['cloudinary_public_id', 'cloudinary_resource_type']);
        });

        Schema::table('quests', function (Blueprint $table) {
            $table->dropColumn('cover_image_url');
        });
    }
};
