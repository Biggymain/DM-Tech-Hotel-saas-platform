<?php

namespace Tests\Feature;

use App\Jobs\SyncChannelAvailabilityJob;
use App\Jobs\SyncChannelPricingJob;
use App\Models\ChannelIntegration;
use App\Models\ChannelRateMapping;
use App\Models\ChannelReservation;
use App\Models\ChannelRoomMapping;
use App\Models\ChannelSyncLog;
use App\Models\Guest;
use App\Models\Hotel;
use App\Models\RatePlan;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChannelManagerTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $roomType;
    protected $room;
    protected $ratePlan;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware();
        $this->seed(\Database\Seeders\RoleAndPermissionSeeder::class);

        $this->hotel = Hotel::create(['name' => 'Grand OTA Hotel']);
        
        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id, 'is_super_admin' => true]);
        
        $role = Role::create(['name' => 'Manager', 'slug' => 'manager']);
        $this->user->roles()->attach($role->id);

        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Deluxe Suite',
            'base_price' => 300
        ]);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => 'D101',
            'status' => 'available'
        ]);

        $this->ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Standard OTA Rate',
            'pricing_strategy' => 'fixed',
            'base_price_modifier' => 0,
            'is_active' => true
        ]);
        
        $this->ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 300, 'hotel_id' => $this->hotel->id]);

        \App\Models\OtaChannel::create([
            'name' => 'Booking.com',
            'provider' => 'booking_com',
            'is_active' => true,
        ]);

        \App\Models\OtaChannel::create([
            'name' => 'Expedia',
            'provider' => 'expedia',
            'is_active' => true,
        ]);

        \App\Models\OtaChannel::create([
            'name' => 'Airbnb',
            'provider' => 'airbnb',
            'is_active' => true,
        ]);

        // Create connection and mapping for webhooks to work without 404
        foreach (['booking_com', 'expedia', 'airbnb'] as $provider) {
            $channel = \App\Models\OtaChannel::where('provider', $provider)->first();
            \App\Models\HotelChannelConnection::create([
                'hotel_id' => $this->hotel->id,
                'ota_channel_id' => $channel->id,
                'api_key' => 'test-api-key', // Added missing non-nullable field
                'status' => 'active'
            ]);

            \App\Models\RoomTypeChannelMap::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $this->roomType->id,
                'ota_channel_id' => $channel->id,
                'external_room_type_id' => 'BKG-RM-1' // Matches payload room_identifier
            ]);
        }
    }

    public function test_channel_integration_creation()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking_com',
            'display_name' => 'Booking HQ',
            'api_key' => 'secret_key',
            'sync_enabled' => true
        ]);

        $this->assertDatabaseHas('channel_integrations', [
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking_com'
        ]);
        $this->assertEquals('secret_key', $integration->api_key);
    }

    public function test_room_and_rate_mapping_to_channel()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'expedia',
        ]);

        ChannelRoomMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'room_type_id' => $this->roomType->id,
            'channel_room_identifier' => 'EXP-DELUXE-123'
        ]);

        ChannelRateMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'rate_plan_id' => $this->ratePlan->id,
            'channel_rate_identifier' => 'EXP-RATE-BASIC'
        ]);

        $this->assertDatabaseHas('channel_room_mappings', [
            'channel_room_identifier' => 'EXP-DELUXE-123'
        ]);
        
        $this->assertDatabaseHas('channel_rate_mappings', [
            'channel_rate_identifier' => 'EXP-RATE-BASIC'
        ]);
    }

    public function test_sync_jobs_dispatched_and_log_created()
    {
        Queue::fake();

        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking_com',
            'sync_enabled' => true,
            'sync_pricing' => true,
            'sync_inventory' => true
        ]);

        ChannelRoomMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'room_type_id' => $this->roomType->id,
            'channel_room_identifier' => 'RM1'
        ]);
        
        ChannelRateMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'rate_plan_id' => $this->ratePlan->id,
            'channel_rate_identifier' => 'RT1'
        ]);

        // Manually trigger the service call which dispatches the job
        $channelManager = app(\App\Services\ChannelManagerService::class);
        $channelManager->syncAvailability($this->roomType);
        $channelManager->syncPricing($this->roomType, $this->ratePlan);

        Queue::assertPushed(SyncChannelAvailabilityJob::class);
        Queue::assertPushed(SyncChannelPricingJob::class);

        // Run the logic directly to test the Sync Log creation
        $syncService = app(\App\Services\OTAInventorySyncService::class);
        $syncService->syncAvailability($this->roomType);

        $otaChannel = \App\Models\OtaChannel::where('provider', 'booking_com')->first();
        $this->assertDatabaseHas('channel_sync_logs', [
            'hotel_id' => $this->hotel->id,
            'ota_channel_id' => $otaChannel->id,
            'operation' => 'availability',
            'status' => 'success'
        ]);
    }

    public function test_webhook_signature_validation()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'airbnb',
            'sync_enabled' => true,
            'webhook_secret' => 'super_secret_hmac_key'
        ]);

        $payload = ['hotel_id' => $this->hotel->id, 'event_type' => 'ping'];
        $jsonPayload = json_encode($payload);
        
        $validSignature = hash_hmac('sha256', $jsonPayload, 'super_secret_hmac_key');
        
        // Invalid
        $response = $this->postJson("/api/v1/channels/airbnb/webhook", $payload, [
            'X-Webhook-Signature' => 'invalid_hash'
        ]);
        $response->assertStatus(401);

        // Valid
        $response = $this->call('POST', "/api/v1/channels/airbnb/webhook", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Webhook-Signature' => $validSignature
        ], $jsonPayload);
        
        $response->assertStatus(200); // Because 'ping' is ignored cleanly
    }

    public function test_channel_reservation_creates_pms_reservation()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking_com',
            'sync_enabled' => true,
            'sync_reservations' => true,
        ]);

        ChannelRoomMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'room_type_id' => $this->roomType->id,
            'channel_room_identifier' => 'BKG-RM-1'
        ]);

        $payload = [
            'hotel_id' => $this->hotel->id,
            'event_type' => 'reservation_created',
            'external_reservation_id' => 'BKG-999',
            'room_identifier' => 'BKG-RM-1',
            'check_in' => now()->addDays(2)->toDateString(),
            'check_out' => now()->addDays(5)->toDateString(),
            'adults' => 2,
            'guest' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.ota@example.com'
            ]
        ];

        $response = $this->postJson("/api/v1/channels/booking_com/webhook", $payload);
        $response->assertStatus(200);

        $this->assertDatabaseHas('reservations', [
            'source' => 'ota',
            'hotel_id' => $this->hotel->id,
        ]);

        $res = Reservation::where('source', 'ota')->first();

        $this->assertDatabaseHas('ota_reservations', [
            'ota_channel_id' => \App\Models\OtaChannel::where('provider', 'booking_com')->first()->id,
            'external_reservation_id' => 'BKG-999',
            'reservation_id' => $res->id
        ]);
    }

    public function test_duplicate_reservation_rejected()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking.com',
            'sync_enabled' => true,
            'sync_reservations' => true,
        ]);

        ChannelRoomMapping::create([
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'room_type_id' => $this->roomType->id,
            'channel_room_identifier' => 'BKG-RM-1'
        ]);

        $payload = [
            'hotel_id' => $this->hotel->id,
            'event_type' => 'reservation_created',
            'external_reservation_id' => 'BKG-DUPLICATE',
            'room_identifier' => 'BKG-RM-1',
            'check_in' => now()->addDays(1)->toDateString(),
            'check_out' => now()->addDays(3)->toDateString(),
            'guest' => ['first_name' => 'A', 'last_name' => 'B']
        ];

        // First ingestion
        $response1 = $this->postJson("/api/v1/channels/booking_com/webhook", $payload);
        $response1->assertStatus(200);

        // Second ingestion (duplicate)
        $response2 = $this->postJson("/api/v1/channels/booking_com/webhook", $payload);
        $response2->assertStatus(200); // Clean exit, ignored
        $response2->assertJson(['status' => 'ignored']);

        // Should only be one 
        $count = \App\Models\OtaReservation::where('external_reservation_id', 'BKG-DUPLICATE')->count();
        $this->assertEquals(1, $count);
    }

    public function test_channel_sync_toggle_disabled()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'expedia',
            'sync_enabled' => false, // Fully disabled via GUI
        ]);

        // Modern sync engine uses HotelChannelConnection status
         \App\Models\HotelChannelConnection::where('hotel_id', $this->hotel->id)
            ->whereHas('otaChannel', function($q) { $q->where('provider', 'expedia'); })
            ->update(['status' => 'disabled']);

        $payload = [
            'hotel_id' => $this->hotel->id,
            'event_type' => 'reservation_created',
        ];

        $response = $this->postJson("/api/v1/channels/expedia/webhook", $payload);
        $response->assertStatus(200);
        $response->assertJson(['reason' => 'Sync disabled']);
    }
}
