<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hotel_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('type')->default('string'); // e.g., string, boolean, json
            $table->timestamps();

            $table->unique(['hotel_id', 'setting_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_settings');
    }
};
