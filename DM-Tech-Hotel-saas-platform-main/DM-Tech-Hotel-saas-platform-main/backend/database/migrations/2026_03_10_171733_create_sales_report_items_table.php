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
        Schema::create('sales_report_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            
            $table->string('item_name');
            $table->string('category_name')->nullable();
            
            $table->decimal('quantity_sold', 10, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            
            $table->timestamps();
            
            $table->index('outlet_id');
            $table->index('menu_item_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_report_items');
    }
};
