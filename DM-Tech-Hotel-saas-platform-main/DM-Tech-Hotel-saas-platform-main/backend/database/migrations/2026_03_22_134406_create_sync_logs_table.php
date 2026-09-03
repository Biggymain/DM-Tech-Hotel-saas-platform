<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('tenant_id')->nullable()->constrained('hotel_groups')->onDelete('cascade');
            $table->foreignId('branch_id')->nullable()->constrained('hotels')->onDelete('cascade');
            $table->string('model_type');
            $table->string('model_id');
            $table->enum('action', ['create', 'update', 'delete']);
            $table->json('payload');
            $table->timestamp('version')->useCurrent();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->string('device_id')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed', 'conflict'])->default('pending');
            $table->timestamp('synced_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->index(['tenant_id', 'branch_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
