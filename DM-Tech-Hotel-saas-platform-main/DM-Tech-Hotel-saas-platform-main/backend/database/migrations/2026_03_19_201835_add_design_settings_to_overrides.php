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
        Schema::table('hotel_website_overrides', function (Blueprint $table) {
            $table->json('design_settings')->nullable()->after('use_group_branding');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_website_overrides', function (Blueprint $table) {
            $table->dropColumn(['design_settings']);
        });
    }
};
