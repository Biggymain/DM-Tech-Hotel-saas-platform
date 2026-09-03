<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Track door lock provider and access configuration per hotel
        Schema::table('hotel_settings', function (Blueprint $table) {
            // door_lock_provider: 'manual' | 'vingcard' | 'salto' | 'dormakaba'
            // These are stored as key-value rows, but we'll add a column to hotels too
        });

        // Digital key & lock event tracking
        Schema::create('digital_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('reservation_id')->constrained('reservations')->cascadeOnDelete();
            $table->string('room_number');
            $table->string('provider');        // 'manual' | 'vingcard' | 'salto' | 'dormakaba'
            $table->string('key_code')->nullable();       // PIN / encoded token
            $table->string('bluetooth_link')->nullable(); // Deep link for BLE key
            $table->string('qr_data')->nullable();        // QR payload
            $table->string('status')->default('active');  // active | expired | revoked
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->json('provider_response')->nullable(); // Raw API response for debugging
            $table->timestamps();
            $table->index(['hotel_id', 'reservation_id']);
        });

        // Lock event audit log (incoming webhooks from VingCard / Salto)
        Schema::create('lock_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->string('room_number');
            $table->string('event_type');    // 'door_open' | 'door_close' | 'invalid_key' | 'low_battery'
            $table->string('trigger_by')->nullable(); // Guest name / key_code that triggered it
            $table->string('provider');
            $table->json('raw_payload')->nullable();
            $table->string('webhook_source')->nullable(); // IP of the sender
            $table->timestamp('occurred_at');
            $table->timestamps();
            $table->index(['hotel_id', 'room_number', 'occurred_at']);
        });

        // Guest notification log
        Schema::create('guest_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hotel_id')->constrained('hotels')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
            $table->string('channel');       // 'email' | 'whatsapp' | 'sms'
            $table->string('recipient');     // email address or phone number
            $table->string('template');      // 'booking_confirmation' | 'digital_key' | 'checkin_reminder'
            $table->string('status')->default('pending'); // pending | sent | failed
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_notifications');
        Schema::dropIfExists('lock_events');
        Schema::dropIfExists('digital_keys');
    }
};
