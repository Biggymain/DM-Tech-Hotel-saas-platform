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
        Schema::table('inventory_items', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique(['hotel_id', 'sku']);
            
            // Add new HUB-aware unique constraint
            // This allows the same SKU to exist in different sub-inventories within the same hotel
            $table->unique(['hotel_id', 'sku', 'outlet_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'sku', 'outlet_id']);
            $table->unique(['hotel_id', 'sku']);
        });
    }
};
