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
            $table->dropColumn('is_active');
            $table->string('status')->default('pending_activation')->after('expires_at'); // pending_activation, active, revoked
            $table->foreignId('waiter_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_portal_sessions', function (Blueprint $table) {
            $table->dropForeign(['waiter_id']);
            $table->dropColumn(['status', 'waiter_id']);
            $table->boolean('is_active')->default(true);
        });
    }
};
