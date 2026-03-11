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
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'last_cleaned_at')) {
                $table->datetime('last_cleaned_at')->nullable();
                $table->datetime('last_inspected_at')->nullable();
                $table->foreignId('assigned_housekeeper_id')->nullable()->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'last_cleaned_at')) {
                $table->dropForeign(['assigned_housekeeper_id']);
                $table->dropColumn([
                    'last_cleaned_at',
                    'last_inspected_at',
                    'assigned_housekeeper_id'
                ]);
            }
        });
    }
};
