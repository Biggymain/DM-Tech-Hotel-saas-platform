<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'pricing_tier_id')) {
                $table->foreignId('pricing_tier_id')->nullable()->constrained('pricing_tiers')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['pricing_tier_id']);
            $table->dropColumn('pricing_tier_id');
        });
    }
};
