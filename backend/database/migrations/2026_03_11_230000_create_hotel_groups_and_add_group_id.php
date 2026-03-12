<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Create the hotel_groups (Organizations) table
        Schema::create('hotel_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');                              // e.g. "DM Tech Hotels Group"
            $table->string('slug')->unique();                    // for routing / display
            $table->string('contact_email')->nullable();
            $table->string('country')->nullable();
            $table->string('currency', 3)->default('USD');       // inherited by branches
            $table->decimal('tax_rate', 5, 2)->default(0);      // inherited by branches
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // 2. Add hotel_group_id to hotels (branch → group relationship)
        Schema::table('hotels', function (Blueprint $table) {
            $table->foreignId('hotel_group_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('hotel_groups')
                  ->nullOnDelete();
        });

        // 3. Add hotel_group_id to users (GROUP_ADMIN users belong to a group, not a single hotel)
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('hotel_group_id')
                  ->nullable()
                  ->after('hotel_id')
                  ->constrained('hotel_groups')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_group_id');
        });

        Schema::table('hotels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hotel_group_id');
        });

        Schema::dropIfExists('hotel_groups');
    }
};
