<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add kitchen station routing to menu items
        Schema::table('menu_items', function (Blueprint $table) {
            // Station name determines which KDS tablet receives this item's orders
            // e.g., 'grill', 'bar', 'pastry', 'cold-kitchen', 'fry'
            $table->string('station_name')->default('main')->after('display_order')
                  ->comment('Kitchen station this item routes to (grill, bar, pastry, main, etc.)');
        });

        // 2. Tighten order status ENUM with new draft/pending states
        // Orders start as 'draft' when waiter is building, 'pending' when fired to kitchen
        Schema::table('orders', function (Blueprint $table) {
            // Add 'waiter_id' so we can push back to the right waiter when order is ready
            $table->foreignId('waiter_id')->nullable()->after('created_by')
                  ->constrained('users')->nullOnDelete()
                  ->comment('The waiter/steward who placed this order — for push-back notification');
            // Extend status to match the 5-state lifecycle
            $table->string('order_status')->default('draft')->after('status')
                  ->comment('Lifecycle: draft → pending → cooking → ready → served');
            // Track which station(s) this order was routed to (JSON array)
            $table->json('routed_stations')->nullable()->after('order_status')
                  ->comment('Stations this order was broadcast to, e.g. [\"grill\", \"bar\"]');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn('station_name');
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['waiter_id']);
            $table->dropColumn(['waiter_id', 'order_status', 'routed_stations']);
        });
    }
};
