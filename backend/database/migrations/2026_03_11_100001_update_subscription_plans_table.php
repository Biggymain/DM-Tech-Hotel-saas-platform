<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_plans', 'billing_cycle')) {
                $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly')->after('price');
            }
            if (!Schema::hasColumn('subscription_plans', 'max_rooms')) {
                $table->integer('max_rooms')->default(0)->after('billing_cycle');
            }
            if (!Schema::hasColumn('subscription_plans', 'max_staff')) {
                $table->integer('max_staff')->default(0)->after('max_rooms');
            }
            if (!Schema::hasColumn('subscription_plans', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('features');
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['billing_cycle', 'max_rooms', 'max_staff', 'is_active']);
        });
    }
};
