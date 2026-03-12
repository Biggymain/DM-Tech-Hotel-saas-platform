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
        Schema::table('folio_items', function (Blueprint $table) {
            $table->foreignId('hotel_id')->after('folio_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('source')->after('amount')->default('ROOM'); // ROOM, POS, LAUNDRY
            $table->string('status')->after('source')->default('PAID'); // PENDING, PAID
            $table->foreignId('inventory_item_id')->after('status')->nullable()->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folio_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('inventory_item_id');
            $table->dropConstrainedForeignId('hotel_id');
            $table->dropColumn(['source', 'status']);
        });
    }
};
