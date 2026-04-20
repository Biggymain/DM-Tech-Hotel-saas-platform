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
        Schema::create('transfer_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('source_staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_staff_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_session_id')->nullable()->constrained('table_sessions')->nullOnDelete();
            $table->foreignId('target_session_id')->nullable()->constrained('table_sessions')->nullOnDelete();
            $table->string('status'); // success, failed
            $table->string('reason')->nullable();
            $table->integer('failed_attempts')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_logs');
    }
};
