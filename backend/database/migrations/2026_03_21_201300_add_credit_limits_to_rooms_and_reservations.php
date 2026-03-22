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
        Schema::table('room_types', function (Blueprint $table) {
            $table->decimal('default_credit_limit', 10, 2)->default(0)->after('base_price');
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->decimal('credit_limit_override', 10, 2)->nullable()->after('total_amount');
            $table->decimal('current_folio_balance', 10, 2)->default(0)->after('credit_limit_override');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropColumn(['credit_limit_override', 'current_folio_balance']);
        });

        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('default_credit_limit');
        });
    }
};
