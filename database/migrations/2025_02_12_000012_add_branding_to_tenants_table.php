<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('logo_dark')->nullable()->after('logo');         // Logo for dark backgrounds
            $table->string('favicon')->nullable()->after('logo_dark');
            $table->string('primary_color', 20)->default('#3B82F6')->after('favicon');
            $table->string('secondary_color', 20)->default('#1E40AF')->after('primary_color');
            $table->string('accent_color', 20)->default('#F59E0B')->after('secondary_color');
            $table->text('description')->nullable()->after('accent_color');
            $table->json('social_links')->nullable()->after('description');
            $table->string('banner_image')->nullable()->after('social_links');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'logo_dark', 'favicon', 'primary_color', 'secondary_color',
                'accent_color', 'description', 'social_links', 'banner_image',
            ]);
        });
    }
};
