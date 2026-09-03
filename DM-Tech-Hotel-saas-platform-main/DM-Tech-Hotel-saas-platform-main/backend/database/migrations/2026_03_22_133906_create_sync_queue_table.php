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
        Schema::create('sync_queue', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('model_type');
            $table->string('model_uuid')->index();
            $table->string('action'); // created, updated, deleted
            $table->json('payload')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_queue');
    }
};
