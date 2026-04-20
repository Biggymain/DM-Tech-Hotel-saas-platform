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
        Schema::create('hardware_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->unsignedBigInteger('branch_id')->nullable(); // Local office/outlet mapping
            $table->string('device_name');
            $table->string('hardware_uuid')->unique();
            $table->string('zone_type')->default('public'); // restricted, public, guest
            $table->boolean('is_verified')->default(false);
            $table->string('status')->default('active'); // active, inactive
            $table->timestamps();

            $table->index(['hotel_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hardware_devices');
    }
};
