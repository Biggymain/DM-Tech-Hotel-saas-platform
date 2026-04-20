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
        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->string('status')->default('pending')->change();
        });

        // Re-map existing statuses
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'requested')->update(['status' => 'pending']);
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'dispatched')->update(['status' => 'in_transit']);
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'received')->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'pending')->update(['status' => 'requested']);
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'in_transit')->update(['status' => 'dispatched']);
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'completed')->update(['status' => 'received']);
        \Illuminate\Support\Facades\DB::table('stock_transfers')->where('status', 'disputed')->update(['status' => 'requested']);

        Schema::table('stock_transfers', function (Blueprint $table) {
            $table->enum('status', ['requested', 'dispatched', 'received'])->default('requested')->change();
        });
    }
};
