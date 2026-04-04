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
        Schema::create('stock_transfers', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $blueprint->foreignId('inventory_item_id')->constrained('inventory_items')->onDelete('cascade');
            
            // Locations involved (e.g., Main Store to Pool Bar)
            $blueprint->integer('from_location_id')->nullable(); 
            $blueprint->integer('to_location_id')->nullable();
            
            // Quantities for the 3-stage confirmation
            $blueprint->decimal('quantity_requested', 12, 2);
            $blueprint->decimal('quantity_dispatched', 12, 2)->nullable();
            $blueprint->decimal('quantity_received', 12, 2)->nullable();
            
            // Chain of Custody Actors
            $blueprint->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('dispatched_by')->nullable()->constrained('users')->onDelete('cascade');
            $blueprint->foreignId('received_by')->nullable()->constrained('users')->onDelete('cascade');
            
            $blueprint->enum('status', ['requested', 'dispatched', 'received'])->default('requested');
            
            $blueprint->timestamp('dispatched_at')->nullable();
            $blueprint->timestamp('received_at')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
