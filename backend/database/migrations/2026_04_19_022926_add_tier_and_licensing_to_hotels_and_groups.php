<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->boolean('is_licensed')->default(false);
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('subscription_tier_id')->nullable()->constrained('subscription_tiers')->nullOnDelete();
        });
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropForeign(['subscription_tier_id']);
            $table->dropColumn('subscription_tier_id');
        });

        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->dropColumn('is_licensed');
        });
    }
};
