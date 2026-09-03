<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add column if it doesn't exist (SQLite safe check)
        if (!Schema::hasColumn('users', 'is_approved')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_approved')->default(false);
            });
        }

        // Ensure support-staff role exists
        DB::table('roles')->updateOrInsert(
            ['slug' => 'supportstaff'],
            [
                'name' => 'Support Staff',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_approved')) {
                $table->dropColumn('is_approved');
            }
        });
    }
};
