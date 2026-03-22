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
        Schema::create('restock_requests', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('hotel_id')->constrained();
            $blueprint->foreignId('branch_id')->constrained();
            $blueprint->foreignId('kitchen_station_id')->constrained();
            $blueprint->foreignId('menu_item_id')->constrained();
            $blueprint->foreignId('requested_by')->constrained('users');
            $blueprint->string('status')->default('pending'); // pending, fulfilled, cancelled
            $blueprint->text('notes')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restock_requests');
    }
};
