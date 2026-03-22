<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hotel_modules', function (Blueprint $table) {
            if (!Schema::hasColumn('hotel_modules', 'branch_id')) {
                // branch_id is mapped as the nullable outlet mapping
                $table->foreignId('branch_id')->nullable()->constrained('outlets')->cascadeOnDelete();
            }
            if (!Schema::hasColumn('hotel_modules', 'version')) {
                $table->string('version')->nullable(); // Module versioning for offline sync
            }
        });
    }

    public function down(): void
    {
        Schema::table('hotel_modules', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'version']);
        });
    }
};
