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
        // 7.3.2 Fix: Drop existing indexes before changing columns to TEXT
        // Explicitly check for index names to maintain compatibility with fresh test DBs vs legacy production.
        $indexes = Schema::getIndexes('guests');
        $indexNames = array_column($indexes, 'name');

        Schema::table('guests', function (Blueprint $table) use ($indexNames) {
            foreach (['first_name', 'last_name', 'email', 'phone'] as $col) {
                $specificName = "guests_{$col}_index";
                if (in_array($specificName, $indexNames)) {
                    $table->dropIndex($specificName);
                }
            }
            
            // Handle composite index from create_guests_table
            if (in_array('guests_hotel_id_last_name_index', $indexNames)) {
                $table->dropIndex(['hotel_id', 'last_name']);
            }
        });

        Schema::table('guests', function (Blueprint $table) {
            $table->string('email_bidx', 64)->nullable()->index();
            $table->string('phone_bidx', 64)->nullable()->index();
            
            $table->text('first_name')->nullable()->change();
            $table->text('last_name')->nullable()->change();
            $table->text('email')->nullable()->change();
            $table->text('phone')->nullable()->change();
            $table->text('identification_type')->nullable()->change();
            $table->text('identification_number')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
