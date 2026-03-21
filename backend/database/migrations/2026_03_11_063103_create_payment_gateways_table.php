<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('gateway_name');
            $table->text('api_key');
            $table->text('api_secret');
            $table->string('webhook_secret')->nullable();
            $table->string('contract_code')->nullable();
            $table->enum('payment_mode', ['online', 'manual', 'hybrid'])->default('hybrid');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['hotel_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateways');
    }
};
