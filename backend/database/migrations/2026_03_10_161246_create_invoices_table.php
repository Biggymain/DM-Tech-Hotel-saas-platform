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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('folio_id')->nullable();
            
            $table->string('invoice_number');
            $table->integer('sequence_number');
            
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('service_charge', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            
            $table->string('currency_code', 3)->default('USD');
            $table->string('currency_symbol')->default('$');
            
            $table->enum('status', ['pending', 'partially_paid', 'paid', 'partially_refunded', 'refunded', 'cancelled'])->default('pending');
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();

            $table->unique(['hotel_id', 'invoice_number']);
            
            $table->index('hotel_id');
            $table->index('order_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
