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
        Schema::table('outlets', function (Blueprint $table) {
            $table->string('slug')->nullable()->after('name');
        });
        
        // Populate slug for existing records
        \App\Models\Outlet::all()->each(function ($outlet) {
            $outlet->slug = \Illuminate\Support\Str::slug($outlet->name);
            $outlet->save();
        });

        Schema::table('outlets', function (Blueprint $table) {
            $table->string('slug')->nullable(false)->change();
            $table->unique(['hotel_id', 'slug']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('outlets', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
