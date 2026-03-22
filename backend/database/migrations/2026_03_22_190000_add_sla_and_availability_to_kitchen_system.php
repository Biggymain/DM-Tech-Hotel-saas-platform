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
        Schema::table('kitchen_tickets', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('kitchen_tickets', 'fired_at')) {
                $blueprint->timestamp('fired_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('kitchen_tickets', 'completed_at')) {
                $blueprint->timestamp('completed_at')->nullable()->after('started_at');
            }
        });

        Schema::table('menu_items', function (Blueprint $blueprint) {
            if (!Schema::hasColumn('menu_items', 'is_available')) {
                $blueprint->boolean('is_available')->default(true)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kitchen_tickets', function (Blueprint $blueprint) {
            $blueprint->dropColumn(['fired_at', 'completed_at']);
        });

        Schema::table('menu_items', function (Blueprint $blueprint) {
            $blueprint->dropColumn('is_available');
        });
    }
};
