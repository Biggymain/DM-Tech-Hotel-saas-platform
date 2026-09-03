<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add booking-engine columns to hotels (branch level)
        Schema::table('hotels', function (Blueprint $table) {
            $table->string('custom_domain')->unique()->nullable()->after('domain')
                  ->comment('e.g. book.royalspringhotel.com — used for white-label booking pages');
            $table->string('subdomain_slug')->unique()->nullable()->after('custom_domain')
                  ->comment('e.g. royal-spring — used for /[slug]/reserve route');
        });

        // 2. Add is_public to room_types (hotels choose which rooms to show publicly)
        Schema::table('room_types', function (Blueprint $table) {
            $table->boolean('is_public')->default(true)->after('capacity')
                  ->comment('If false, this room type is hidden from the public booking engine');
        });

        // 3. Add theme colors and group-level payment keys to hotel_groups
        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->string('primary_color', 7)->default('#6366f1')->after('tax_rate')
                  ->comment('HEX brand color for public booking pages');
            $table->string('accent_color', 7)->default('#8b5cf6')->after('primary_color');
            $table->string('logo_url')->nullable()->after('accent_color');
            // Group-level fallback payment keys (encrypted)
            $table->text('paystack_public_key')->nullable()->after('logo_url');
            $table->text('paystack_secret_key')->nullable()->after('paystack_public_key');
            $table->text('flutterwave_public_key')->nullable()->after('paystack_secret_key');
            $table->text('flutterwave_secret_key')->nullable()->after('flutterwave_public_key');
        });
    }

    public function down(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            $table->dropColumn(['custom_domain', 'subdomain_slug']);
        });
        Schema::table('room_types', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
        Schema::table('hotel_groups', function (Blueprint $table) {
            $table->dropColumn([
                'primary_color', 'accent_color', 'logo_url',
                'paystack_public_key', 'paystack_secret_key',
                'flutterwave_public_key', 'flutterwave_secret_key',
            ]);
        });
    }
};
