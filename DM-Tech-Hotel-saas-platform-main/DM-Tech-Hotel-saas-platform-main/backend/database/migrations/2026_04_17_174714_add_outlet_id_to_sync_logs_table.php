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
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->foreignId('outlet_id')->nullable()->after('branch_id')->index();
            $table->index(['outlet_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('sync_logs', function (Blueprint $table) {
            $table->dropIndex(['outlet_id', 'status']);
            $table->dropColumn('outlet_id');
        });
    }
};
