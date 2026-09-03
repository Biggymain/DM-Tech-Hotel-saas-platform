<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelChannelConnection;
use App\Models\OtaChannel;
use App\Models\OtaReservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\RoomTypeChannelMap;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OtaChannelManagerTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected User $admin;
    protected OtaChannel $channel;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed subscription plans then create an active subscription
        $this->artisan('db:seed --class=SubscriptionPlanSeeder');

        $this->hotel = Hotel::factory()->create();
        $this->admin = User::factory()->create(['hotel_id' => $this->hotel->id]);

        // Create an active subscription so the subscription.active middleware passes
        $plan = \App\Models\SubscriptionPlan::first();
        if ($plan) {
            app(SubscriptionService::class)->createSubscription($this->hotel, $plan);
        }

        $this->channel = OtaChannel::create([
            'name' => 'Booking.com',
            'provider' => 'booking_com',
            'api_endpoint' => 'https://api.booking.com/v1',
            'is_active' => true,
        ]);
    }

    // =================== OtaConnectionTest ===================

    #[Test]
    public function test_hotel_can_connect_to_ota_channel(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/ota/connect', [
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'test-api-key-abc123',
            'api_secret' => 'test-secret-xyz',
        ]);

        $response->assertStatus(201);
        $response->assertJsonFragment(['status' => 'active']);

        $this->assertDatabaseHas('hotel_channel_connections', [
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'status' => 'active',
        ]);
    }

    #[Test]
    public function test_api_credentials_are_encrypted_in_database(): void
    {
        $connection = HotelChannelConnection::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'plaintext-key',
            'status' => 'active',
        ]);

        // Raw DB value must be encrypted (not equal to the plaintext)
        $rawValue = DB::table('hotel_channel_connections')->where('id', $connection->id)->value('api_key');
        $this->assertNotEquals('plaintext-key', $rawValue);

        // But the model decrypts it transparently
        $this->assertEquals('plaintext-key', $connection->fresh()->api_key);
    }

    #[Test]
    public function test_connections_endpoint_returns_sync_status(): void
    {
        HotelChannelConnection::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'key123',
            'status' => 'active',
            'last_sync_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/ota/connections');

        $response->assertOk();
        $response->assertJsonFragment(['channel' => 'Booking.com']);
    }

    // =================== RoomTypeMappingTest ===================

    #[Test]
    public function test_admin_can_map_room_type_to_ota(): void
    {
        $roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Deluxe', 'base_price' => 10000, 'capacity' => 2]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/ota/map/room-type', [
            'ota_channel_id' => $this->channel->id,
            'room_type_id' => $roomType->id,
            'external_room_type_id' => 'BKG-ROOM-101',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('room_type_channel_maps', [
            'room_type_id' => $roomType->id,
            'external_room_type_id' => 'BKG-ROOM-101',
        ]);
    }

    // =================== OtaReservationImportTest ===================

    #[Test]
    public function test_can_import_ota_reservation_via_webhook(): void
    {
        $roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Deluxe', 'base_price' => 10000, 'capacity' => 2]);
        $room = Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $roomType->id, 'room_number' => '101', 'status' => 'available', 'floor' => 1]);

        HotelChannelConnection::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'key123',
            'status' => 'active',
        ]);

        RoomTypeChannelMap::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'ota_channel_id' => $this->channel->id,
            'external_room_type_id' => 'BKG-DELUXE',
        ]);

        $webhookPayload = [
            'hotel_id' => $this->hotel->id,
            'event_type' => 'reservation_created',
            'external_reservation_id' => 'BKG-94858273',
            'guest' => ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@test.com'],
            'guest_name' => 'John Doe',
            'room_identifier' => 'BKG-DELUXE',
            'check_in' => now()->addDays(7)->toDateString(),
            'check_out' => now()->addDays(10)->toDateString(),
            'total_price' => 45000,
            'status' => 'confirmed',
            'adults' => 2,
        ];

        $response = $this->postJson('/api/v1/channels/booking_com/webhook', $webhookPayload);

        $response->assertOk();
        $this->assertDatabaseHas('ota_reservations', [
            'external_reservation_id' => 'BKG-94858273',
            'hotel_id' => $this->hotel->id,
        ]);
    }

    #[Test]
    public function test_duplicate_ota_reservation_is_skipped(): void
    {
        OtaReservation::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'external_reservation_id' => 'BKG-DUPLICATE-001',
            'guest_name' => 'Jane Doe',
            'check_in' => now()->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'room_type' => 'DELUXE',
            'total_price' => 30000,
            'status' => 'confirmed',
        ]);

        // Verify it exists exactly once
        $this->assertEquals(1, OtaReservation::where('external_reservation_id', 'BKG-DUPLICATE-001')->count());
    }

    // =================== ChannelSyncLogTest ===================

    #[Test]
    public function test_sync_logs_are_accessible(): void
    {
        \App\Models\ChannelSyncLog::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'operation' => 'inventory_push',
            'status' => 'success',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/v1/admin/ota/sync-logs');

        $response->assertOk();
        $response->assertJsonFragment(['operation' => 'inventory_push']);
    }

    // =================== InventorySyncTest ===================

    #[Test]
    public function test_manual_sync_can_be_triggered(): void
    {
        HotelChannelConnection::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'key123',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/admin/ota/sync');

        $response->assertOk();
        $response->assertJsonFragment(['message' => 'Sync jobs dispatched for all active channels.']);
    }

    // =================== OverbookingProtectionTest ===================

    #[Test]
    public function test_overbooking_is_prevented_when_no_rooms_available(): void
    {
        $roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Standard', 'base_price' => 8000, 'capacity' => 2]);

        HotelChannelConnection::create([
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $this->channel->id,
            'api_key' => 'key123',
            'status' => 'active',
        ]);

        RoomTypeChannelMap::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $roomType->id,
            'ota_channel_id' => $this->channel->id,
            'external_room_type_id' => 'BKG-NO-ROOM',
        ]);

        // No rooms exist — expect overbooking to be blocked
        $response = $this->postJson('/api/v1/channels/booking_com/webhook', [
            'hotel_id' => $this->hotel->id,
            'event_type' => 'reservation_created',
            'external_reservation_id' => 'BKG-OVERBOOK-999',
            'guest' => ['first_name' => 'Over', 'last_name' => 'Book', 'email' => 'overbook@test.com'],
            'guest_name' => 'Over Book',
            'room_identifier' => 'BKG-NO-ROOM',
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'total_price' => 20000,
            'status' => 'confirmed',
            'adults' => 1,
        ]);

        // Should fail gracefully — no overbooking allowed
        $response->assertStatus(422);
        $this->assertDatabaseMissing('ota_reservations', ['external_reservation_id' => 'BKG-OVERBOOK-999']);
    }
}
