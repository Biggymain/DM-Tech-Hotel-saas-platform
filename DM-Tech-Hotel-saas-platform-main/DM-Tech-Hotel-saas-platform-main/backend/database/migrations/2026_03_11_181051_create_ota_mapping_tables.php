<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_type_channel_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('room_type_id')->constrained()->onDelete('cascade');
            $table->foreignId('ota_channel_id')->constrained('ota_channels')->onDelete('cascade');
            $table->string('external_room_type_id');
            $table->timestamps();

            $table->unique(['room_type_id', 'ota_channel_id']);
            $table->index('hotel_id');
        });

        Schema::create('rate_plan_channel_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->foreignId('rate_plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('ota_channel_id')->constrained('ota_channels')->onDelete('cascade');
            $table->string('external_rate_plan_id');
            $table->timestamps();

            $table->unique(['rate_plan_id', 'ota_channel_id']);
            $table->index('hotel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_plan_channel_maps');
        Schema::dropIfExists('room_type_channel_maps');
    }
};
