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
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            
            $table->enum('issue_type', ['plumbing', 'electrical', 'furniture', 'hvac', 'other']);
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('low');
            $table->text('description');
            
            $table->enum('status', ['open', 'assigned', 'in_progress', 'resolved'])->default('open');
            $table->datetime('resolved_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['hotel_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_requests');
    }
};
