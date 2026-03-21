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
        Schema::create('channel_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('channel_integration_id')->constrained()->onDelete('cascade');
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null');
            $table->string('channel_reservation_id');
            $table->json('raw_payload')->nullable();
            $table->dateTime('imported_at');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate ingestion
            $table->unique(['channel_integration_id', 'channel_reservation_id'], 'channel_res_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_reservations');
    }
};
