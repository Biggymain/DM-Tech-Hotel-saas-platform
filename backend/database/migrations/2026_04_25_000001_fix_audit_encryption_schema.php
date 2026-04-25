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
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->longText('old_values')->nullable()->change();
            $table->longText('new_values')->nullable()->change();
            $table->text('reason')->nullable()->change(); // Prevents truncation
        });

        Schema::table('hardware_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('hardware_devices', 'expires_at')) {
                $table->timestamp('expires_at')->nullable();
            }
        });

        Schema::table('guest_portal_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('guest_portal_sessions', 'requires_reauth')) {
                $table->boolean('requires_reauth')->default(false);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->json('old_values')->nullable()->change();
            $table->json('new_values')->nullable()->change();
            $table->string('reason')->nullable()->change();
        });

        Schema::table('hardware_devices', function (Blueprint $table) {
            if (Schema::hasColumn('hardware_devices', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
