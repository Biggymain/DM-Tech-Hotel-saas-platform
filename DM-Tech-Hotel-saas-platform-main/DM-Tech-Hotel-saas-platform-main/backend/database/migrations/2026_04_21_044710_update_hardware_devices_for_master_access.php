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
        Schema::table('hardware_devices', function (Blueprint $table) {
            $table->string('hardware_hash')->nullable()->unique()->after('hardware_uuid');
            $table->string('access_level')->default('terminal')->after('hardware_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hardware_devices', function (Blueprint $table) {
            $table->dropColumn(['hardware_hash', 'access_level']);
        });
    }
};
