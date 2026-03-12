<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Stations lookup table (must exist before we add the FK) ──────────
        if (!Schema::hasTable('stations')) {
            Schema::create('stations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
                $table->foreignId('outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
                $table->string('name');
                $table->string('slug');
                $table->string('display_color')->default('#f59e0b');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['hotel_id', 'slug']);
            });
        }

        // ── 2. Add station_id FK to menu_items ───────────────────────────────
        Schema::table('menu_items', function (Blueprint $table) {
            if (!Schema::hasColumn('menu_items', 'station_id')) {
                $table->foreignId('station_id')
                      ->nullable()
                      ->after('station_name')
                      ->constrained('stations')
                      ->nullOnDelete()
                      ->comment('FK to stations table for strict channel routing');
            }
        });

        // ── 3. Per-item status on order_items ────────────────────────────────
        Schema::table('order_items', function (Blueprint $table) {
            if (!Schema::hasColumn('order_items', 'item_status')) {
                $table->string('item_status')->default('pending')
                      ->after('subtotal');
            }
        });

        // ── 4. Waitress confirmation flag on orders ───────────────────────────
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'is_confirmed')) {
                $table->boolean('is_confirmed')->default(false)
                      ->after('order_status');
            }
        });
    }


    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropForeign(['station_id']);
            $table->dropColumn('station_id');
        });
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn('item_status');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('is_confirmed');
        });
        Schema::dropIfExists('stations');
    }
};
