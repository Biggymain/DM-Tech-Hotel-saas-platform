<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0.00);
            $table->integer('room_limit')->default(0); // 0 means no limit for tier 5, or handle unlimited logically. 0 for tier 1/2? Let's use nullable for unlimited. Wait, prompt says "Max 15 Rooms", "Max 40 Rooms". Tier 5: Unlimited. Tier 1/2: POS/Supermarket (Rooms not supported probably, so 0 is fine). Let's use integer.
            $table->json('features')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_tiers');
    }
};
