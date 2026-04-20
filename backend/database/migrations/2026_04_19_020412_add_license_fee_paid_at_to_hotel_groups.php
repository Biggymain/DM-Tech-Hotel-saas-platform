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
        Schema::disableForeignKeyConstraints();
        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->timestamp('license_fee_paid_at')->nullable()->after('is_licensed');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->dropColumn('license_fee_paid_at');
        });
    }
};
