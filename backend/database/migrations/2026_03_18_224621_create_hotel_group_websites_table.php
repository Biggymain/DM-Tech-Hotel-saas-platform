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
        Schema::create('hotel_group_websites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_group_id')->unique()->constrained()->onDelete('cascade');
            $table->string('slug')->unique(); // public URL slug
            $table->string('title');
            $table->text('description')->nullable(); // Hero description
            $table->text('about_text')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('primary_color')->default('#4f46e5'); // indigo-600
            $table->string('secondary_color')->default('#7c3aed'); // purple-600
            $table->json('social_links')->nullable(); // facebook, twitter, etc
            $table->json('features')->nullable(); // array of features to highlight
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotel_group_websites');
    }
};
