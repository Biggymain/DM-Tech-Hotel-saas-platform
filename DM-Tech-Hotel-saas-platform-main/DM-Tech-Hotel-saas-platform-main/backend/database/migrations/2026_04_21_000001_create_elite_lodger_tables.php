<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->text('username')->nullable()->after('last_name');
            $table->string('username_bindex')->nullable()->index()->after('username');
            $table->boolean('is_onboarded')->default(false)->after('loyalty_points');
        });

        Schema::create('loyalty_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // 'item' or 'service'
            $table->integer('point_cost');
            $table->foreignId('inventory_item_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('loyalty_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('guest_id')->constrained()->cascadeOnDelete();
            $table->foreignId('outlet_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type'); // 'earn', 'redeem', 'manual_adjustment'
            $table->integer('points');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('processed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_transactions');
        Schema::dropIfExists('loyalty_products');
        Schema::table('guests', function (Blueprint $table) {
            // SQLite safe: drop index then column
            $table->dropIndex(['username_bindex']);
            $table->dropColumn(['username', 'username_bindex', 'is_onboarded']);
        });
    }
};
