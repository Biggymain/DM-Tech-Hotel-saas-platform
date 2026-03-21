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
        Schema::create('sales_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            
            $table->date('report_date');
            $table->enum('report_type', ['daily', 'weekly', 'monthly'])->default('daily');
            
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->integer('total_orders')->default(0);
            $table->decimal('total_tax', 15, 2)->default(0);
            $table->decimal('total_service_charge', 15, 2)->default(0);
            
            $table->string('currency_code', 3)->default('USD');
            $table->string('currency_symbol')->default('$');
            
            $table->timestamps();
            
            // Avoid duplicate headers for same exact date & type
            $table->unique(['hotel_id', 'report_date', 'report_type']);
            $table->index(['hotel_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_reports');
    }
};
