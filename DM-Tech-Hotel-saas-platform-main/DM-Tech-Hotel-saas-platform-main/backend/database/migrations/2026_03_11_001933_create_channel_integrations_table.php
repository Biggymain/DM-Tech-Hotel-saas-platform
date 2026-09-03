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
        Schema::create('channel_integrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->string('channel_name');
            $table->string('display_name')->nullable();
            $table->text('api_key')->nullable();
            $table->text('api_secret')->nullable();
            $table->string('endpoint_url')->nullable();
            $table->string('webhook_secret')->nullable();
            
            // GUI Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->boolean('sync_pricing')->default(true);
            $table->boolean('sync_inventory')->default(true);
            $table->boolean('sync_reservations')->default(true);
            
            $table->dateTime('last_sync_at')->nullable();
            $table->timestamps();
            
            $table->index(['hotel_id', 'channel_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel_integrations');
    }
};
