<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $row) {
            $row->boolean('is_on_duty')->default(false);
            $row->timestamp('last_duty_toggle_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $row) {
            $row->dropColumn(['is_on_duty', 'last_duty_toggle_at']);
        });
    }
};
