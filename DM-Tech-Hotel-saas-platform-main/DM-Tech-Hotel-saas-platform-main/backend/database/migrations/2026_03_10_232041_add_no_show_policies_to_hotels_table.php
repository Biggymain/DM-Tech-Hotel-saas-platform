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
        Schema::table('hotels', function (Blueprint $table) {
            $table->integer('reservation_deadline_hours_before_checkin')->nullable();
            $table->integer('reservation_grace_hours')->nullable();
            $table->string('no_show_penalty_type')->nullable(); // deposit, first_night, full_stay
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn([
                'reservation_deadline_hours_before_checkin',
                'reservation_grace_hours',
                'no_show_penalty_type'
            ]);
        });
    }
};
