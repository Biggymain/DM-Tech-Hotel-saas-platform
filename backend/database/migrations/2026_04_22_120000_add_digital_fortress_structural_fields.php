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
        // Add structure to hotel_settings
        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->boolean('use_internal_website')->default(true)->after('hotel_id');
        });

        // Add structure to hotel_website_overrides
        Schema::table('hotel_website_overrides', function (Blueprint $table) {
            $table->integer('template_id')->default(1)->after('hotel_id');
        });

        // Add OTA token to hotels
        Schema::table('hotels', function (Blueprint $table) {
            $table->text('ota_token')->nullable()->after('pos_terminal_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn('ota_token');
        });

        Schema::table('hotel_website_overrides', function (Blueprint $table) {
            $table->dropColumn('template_id');
        });

        Schema::table('hotel_settings', function (Blueprint $table) {
            $table->dropColumn('use_internal_website');
        });
    }
};
