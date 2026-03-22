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
        Schema::table('menu_items', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('menu_category_id')->constrained('kitchen_stations')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('outlet_id')->constrained('kitchen_stations')->nullOnDelete();
        });

        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->foreignId('kitchen_station_id')->nullable()->after('outlet_id')->constrained('kitchen_stations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_tickets', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn('kitchen_station_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn('kitchen_station_id');
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['kitchen_station_id']);
            $table->dropColumn('kitchen_station_id');
        });
    }
};
