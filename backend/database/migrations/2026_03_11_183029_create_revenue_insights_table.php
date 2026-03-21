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
        Schema::create('revenue_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->decimal('occupancy_rate', 5, 2);
            $table->decimal('avg_daily_rate', 10, 2);
            $table->decimal('revpar', 10, 2);
            $table->integer('demand_score');
            $table->json('recommended_rate_adjustment')->nullable();
            $table->timestamps();

            $table->unique(['hotel_id', 'date']);
            $table->index(['hotel_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue_insights');
    }
};
