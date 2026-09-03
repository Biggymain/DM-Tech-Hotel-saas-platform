<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('guest_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('folio_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_gateway');
            $table->string('gateway_transaction_id')->nullable()->unique();
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->enum('status', ['pending', 'authorized', 'captured', 'failed', 'refunded', 'manual_pending', 'manual_confirmed'])->default('pending');
            $table->enum('payment_source', ['guest_portal', 'restaurant_pos', 'frontdesk', 'room_service', 'manual'])->default('guest_portal');
            $table->json('context_metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['hotel_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_transactions');
    }
};
