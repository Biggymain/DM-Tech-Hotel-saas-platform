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
        // 1. Add id_scan_url to guests
        Schema::table('guests', function (Blueprint $table) {
            $table->text('id_scan_url')->nullable()->after('identification_number');
        });

        // 2. Cleanup absolute URLs in hotel_groups
        $hotelGroups = DB::table('hotel_groups')->whereNotNull('logo_url')->get();
        foreach ($hotelGroups as $group) {
            if (filter_var($group->logo_url, FILTER_VALIDATE_URL)) {
                $path = parse_url($group->logo_url, PHP_URL_PATH);
                // Strip leading /storage or bucket name if present
                $path = ltrim($path, '/');
                $path = str_replace('storage/', '', $path);
                
                DB::table('hotel_groups')->where('id', $group->id)->update(['logo_url' => $path]);
            }
        }

        // 3. Cleanup absolute URLs in guests (in case any were seeded/manually added)
        $guests = DB::table('guests')->whereNotNull('id_scan_url')->get();
        foreach ($guests as $guest) {
            if (filter_var($guest->id_scan_url, FILTER_VALIDATE_URL)) {
                $path = parse_url($guest->id_scan_url, PHP_URL_PATH);
                $path = ltrim($path, '/');
                $path = str_replace('storage/', '', $path);
                
                DB::table('guests')->where('id', $guest->id)->update(['id_scan_url' => $path]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropColumn('id_scan_url');
        });
    }
};
