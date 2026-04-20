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
        Schema::table('order_items', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('subtotal'); // pending, served, paid, voided, returned
            $table->foreignId('waiter_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->foreignId('table_session_id')->nullable()->after('waiter_id')->constrained('table_sessions')->nullOnDelete();
        });

        Schema::table('table_sessions', function (Blueprint $table) {
            $table->foreignId('opened_by_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->unsignedBigInteger('transfer_log_id')->nullable()->after('total');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn('transfer_log_id');
        });

        Schema::table('table_sessions', function (Blueprint $table) {
            $table->dropForeign(['opened_by_id']);
            $table->dropColumn('opened_by_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['table_session_id']);
            $table->dropForeign(['waiter_id']);
            $table->dropColumn(['status', 'waiter_id', 'table_session_id']);
        });
    }
};
