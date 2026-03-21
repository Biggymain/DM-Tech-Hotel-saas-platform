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
        Schema::create('kitchen_ticket_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kitchen_ticket_id')->constrained('kitchen_tickets', 'id', 'fk_ktsh_ktid')->onDelete('cascade');
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kitchen_ticket_status_histories');
    }
};
