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
        // Add hotel_id to roles
        Schema::table('roles', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Drop the old slug unique constraint since it's only unique per hotel now
            $table->dropUnique(['slug']);
            $table->unique(['hotel_id', 'slug']);
        });

        // Add hotel_id to permissions
        Schema::table('permissions', function (Blueprint $table) {
            $table->foreignId('hotel_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            // Drop old slug unique constraint
            $table->dropUnique(['slug']);
            $table->unique(['hotel_id', 'slug']);
        });

        // Recreate role_permissions instead of renaming and dropping PKs to avoid Postgres issues
        Schema::dropIfExists('permission_role');
        Schema::create('role_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['permission_id', 'role_id', 'hotel_id']);
        });

        // Create user_roles
        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['user_id', 'role_id', 'hotel_id']);
        });

        // Drop role_id from users
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
            $table->dropColumn('role_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('role_id')->nullable()->after('hotel_id')->constrained()->nullOnDelete();
        });

        Schema::dropIfExists('user_roles');

        Schema::dropIfExists('role_permissions');
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'slug']);
            $table->dropForeign(['hotel_id']);
            $table->dropColumn('hotel_id');
            $table->unique('slug');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['hotel_id', 'slug']);
            $table->dropForeign(['hotel_id']);
            $table->dropColumn('hotel_id');
            $table->unique('slug');
        });
    }
};
