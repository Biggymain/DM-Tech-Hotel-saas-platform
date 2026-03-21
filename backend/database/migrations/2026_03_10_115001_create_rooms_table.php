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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_type_id')->constrained()->cascadeOnDelete();
            $table->string('room_number');
            $table->string('floor')->nullable();
            
            $table->enum('status', ['available', 'occupied', 'maintenance', 'out_of_order'])->default('available');
            $table->enum('housekeeping_status', ['clean', 'dirty', 'cleaning', 'inspecting'])->default('clean');
            
            $table->text('maintenance_notes')->nullable();
            $table->timestamp('maintenance_until')->nullable();
            
            $table->timestamps();
            
            $table->index(['hotel_id', 'status']);
            $table->unique(['hotel_id', 'room_number']); // Room numbers must be unique per hotel
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
