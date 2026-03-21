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
        Schema::create('ota_channels', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('provider'); // booking_com, expedia, airbnb
            $table->string('api_endpoint')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('hotel_channel_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('ota_channel_id')->constrained('ota_channels')->onDelete('cascade');
            $table->text('api_key'); // encrypted
            $table->text('api_secret')->nullable(); // encrypted
            $table->text('refresh_token')->nullable(); // encrypted
            $table->string('status')->default('active'); // active, inactive, disconnected
            $table->dateTime('last_sync_at')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'ota_channel_id']);
        });

        Schema::create('ota_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('ota_channel_id')->constrained('ota_channels')->onDelete('cascade');
            $table->string('external_reservation_id');
            $table->string('guest_name');
            $table->date('check_in');
            $table->date('check_out');
            $table->string('room_type'); // OTA room type name
            $table->decimal('total_price', 10, 2);
            $table->string('status');
            $table->json('raw_payload')->nullable();
            $table->foreignId('reservation_id')->nullable()->constrained()->onDelete('set null'); // Link to internal reservation
            $table->timestamps();

            $table->unique(['ota_channel_id', 'external_reservation_id']);
            $table->index('hotel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ota_reservations');
        Schema::dropIfExists('hotel_channel_connections');
        Schema::dropIfExists('ota_channels');
    }
};
