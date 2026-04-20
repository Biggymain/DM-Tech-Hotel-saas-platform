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
