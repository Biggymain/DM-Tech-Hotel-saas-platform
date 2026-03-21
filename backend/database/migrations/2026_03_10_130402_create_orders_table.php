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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->nullable()->constrained()->nullOnDelete();
            $table->string('table_number')->nullable();
            $table->string('order_number');
            $table->enum('order_source', ['pos', 'qr_table', 'qr_room', 'room_service', 'mobile'])->default('pos');
            $table->string('status')->default('pending'); // pending, confirmed, preparing, ready, served, closed, cancelled
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_status')->default('unpaid'); // unpaid, partial, paid
            $table->enum('payment_method', ['cash', 'card', 'room_charge', 'manual', 'online_gateway'])->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['hotel_id', 'order_number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
