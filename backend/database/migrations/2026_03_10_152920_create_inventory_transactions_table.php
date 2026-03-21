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
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('inventory_item_id')->constrained('inventory_items')->cascadeOnDelete();
            $table->enum('type', ['in', 'out', 'adjustment', 'transfer']);
            $table->decimal('quantity', 10, 2);
            $table->string('reference_type')->nullable(); // e.g., 'order', 'manual', 'purchase'
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('source_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->foreignId('destination_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->string('ip_address')->nullable();
            $table->string('device')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Indexes
            $table->index('hotel_id');
            $table->index('inventory_item_id');
            $table->index(['reference_type', 'reference_id']);
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
