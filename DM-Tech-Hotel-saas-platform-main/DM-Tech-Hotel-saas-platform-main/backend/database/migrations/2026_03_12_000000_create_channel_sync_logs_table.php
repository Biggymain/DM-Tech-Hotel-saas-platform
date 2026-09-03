<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channel_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('ota_channel_id')->constrained('ota_channels')->onDelete('cascade');
            $table->string('operation'); // inventory_push, reservation_pull, rate_update, webhook
            $table->string('status');    // success, failed, skipped, received
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'ota_channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_sync_logs');
    }
};
