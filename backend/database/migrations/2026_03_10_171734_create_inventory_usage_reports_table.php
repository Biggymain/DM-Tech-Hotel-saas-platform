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
        Schema::create('inventory_usage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained()->cascadeOnDelete();
            
            $table->date('report_date');
            $table->decimal('quantity_used', 10, 2)->default(0);
            $table->decimal('cost_value', 15, 2)->default(0);
            
            $table->timestamps();
            
            $table->unique(['hotel_id', 'outlet_id', 'inventory_item_id', 'report_date'], 'inv_usage_report_unique');
            $table->index(['hotel_id', 'report_date']);
            $table->index('outlet_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_usage_reports');
    }
};
