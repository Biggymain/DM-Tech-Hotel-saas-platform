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
        Schema::disableForeignKeyConstraints();

        // 7.3.2 Fix: Drop existing constraints before changing columns to TEXT
        // We drop composite indexes and foreign keys that would otherwise block types changes in MySQL.
        $indexes = Schema::getIndexes('guests');
        $indexNames = array_column($indexes, 'name');

        Schema::table('guests', function (Blueprint $table) use ($indexNames) {
            // Drop individual column indexes if they exist
            foreach (['first_name', 'last_name', 'email', 'phone'] as $col) {
                $specificName = "guests_{$col}_index";
                if (in_array($specificName, $indexNames)) {
                    $table->dropIndex($specificName);
                }
            }
            
            // Drop composite index
            if (in_array('guests_hotel_id_last_name_index', $indexNames)) {
                $table->dropIndex('guests_hotel_id_last_name_index');
            }

            // Surgical Drop of the Foreign Key
            // Laravel's dropForeign handles different database names correctly for 'hotel_id'
            try {
                $table->dropForeign(['hotel_id']);
            } catch (\Exception $e) {
                // Ignore if not present
            }
        });

        // Effect the column type change to TEXT for encryption support
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

        // Re-establish Integrity
        Schema::table('guests', function (Blueprint $table) {
            $table->foreign('hotel_id')
                  ->references('id')
                  ->on('hotels')
                  ->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
