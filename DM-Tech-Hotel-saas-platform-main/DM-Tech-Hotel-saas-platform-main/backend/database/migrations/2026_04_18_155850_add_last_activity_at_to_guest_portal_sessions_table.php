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
        Schema::table('guest_portal_sessions', function (Blueprint $table) {
            $table->timestamp('last_activity_at')->nullable()->after('waiter_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_portal_sessions', function (Blueprint $table) {
            $table->dropColumn('last_activity_at');
        });
    }
};
