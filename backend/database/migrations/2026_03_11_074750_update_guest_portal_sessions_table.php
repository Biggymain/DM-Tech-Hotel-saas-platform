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
            $table->unsignedBigInteger('room_id')->nullable()->change();
            $table->string('context_type')->default('room')->after('reservation_id');
            $table->unsignedBigInteger('context_id')->nullable()->after('context_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_portal_sessions', function (Blueprint $table) {
            $table->unsignedBigInteger('room_id')->nullable(false)->change();
            $table->dropColumn(['context_type', 'context_id']);
        });
    }
};
