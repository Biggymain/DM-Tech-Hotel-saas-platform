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
        Schema::table('guests', function (Blueprint $table) {
            $table->boolean('is_vip')->default(false)->after('identification_number');
            $table->integer('loyalty_points')->default(0)->after('is_vip');
            $table->string('status')->default('active')->index()->after('loyalty_points');
        });
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['is_vip', 'loyalty_points', 'status']);
        });
    }
};
