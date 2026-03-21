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
        Schema::create('hotel_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->nullable(); // e.g. Conference, Festival, Holiday
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('impact_level', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'start_date', 'end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_events');
    }
};
