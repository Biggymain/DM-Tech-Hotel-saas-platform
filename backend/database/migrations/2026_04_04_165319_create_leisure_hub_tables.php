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
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('hotel_id')->constrained()->onDelete('cascade');
            $table->string('type'); // daily, weekly, monthly, yearly
            $table->decimal('price_paid', 10, 2);
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('leisure_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('outlet_id')->constrained()->onDelete('cascade');
            $table->timestamp('entry_time')->useCurrent();
            $table->string('method'); // PIN, RFID, QR
            $table->boolean('allow')->default(true);
            $table->timestamps();
        });

        Schema::create('staff_daily_pins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('daily_pin');
            $table->timestamp('expires_at');
            $table->timestamps();
            $table->index(['user_id', 'daily_pin']);
        });

        Schema::create('leisure_bundles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_item_id')->constrained()->onDelete('cascade');
            $table->foreignId('inventory_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leisure_bundles');
        Schema::dropIfExists('staff_daily_pins');
        Schema::dropIfExists('leisure_access_logs');
        Schema::dropIfExists('memberships');
    }
};
