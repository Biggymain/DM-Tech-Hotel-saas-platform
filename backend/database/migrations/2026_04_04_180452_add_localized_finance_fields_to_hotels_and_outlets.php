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
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
            $table->string('pos_terminal_id')->nullable();
            $table->json('stakeholder_emails')->nullable();
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number', 'account_name', 'pos_terminal_id', 'stakeholder_emails']);
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn(['bank_name', 'account_number', 'account_name']);
        });
    }
};
