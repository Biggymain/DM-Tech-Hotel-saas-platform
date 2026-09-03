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
        Schema::create('hotel_website_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->unique()->constrained()->onDelete('cascade');
            $table->string('custom_title')->nullable();
            $table->text('custom_description')->nullable();
            $table->text('custom_about_text')->nullable();
            $table->string('primary_image_url')->nullable();
            $table->string('secondary_image_url')->nullable();
            $table->boolean('use_group_branding')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_website_overrides');
    }
};
