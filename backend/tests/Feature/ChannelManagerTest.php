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
    }

    public function test_channel_integration_creation()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking.com',
            'display_name' => 'Booking HQ',
            'api_key' => 'secret_key',
            'sync_enabled' => true
        ]);

        $this->assertDatabaseHas('channel_integrations', [
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'booking.com'
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
            'channel_name' => 'booking.com',
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

        $this->assertDatabaseHas('channel_sync_logs', [
            'hotel_id' => $this->hotel->id,
            'channel_integration_id' => $integration->id,
            'sync_type' => 'availability',
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

        $payload = ['hotel_identifier' => $this->hotel->id, 'event_type' => 'ping'];
        $jsonPayload = json_encode($payload);
        
        $validSignature = hash_hmac('sha256', $jsonPayload, 'super_secret_hmac_key');
        
        // Invalid
        $response = $this->postJson("/api/v1/channels/webhook/airbnb", $payload, [
            'X-Channel-Signature' => 'invalid_hash'
        ]);
        $response->assertStatus(401);

        // Valid
        $response = $this->call('POST', "/api/v1/channels/webhook/airbnb", [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X-Channel-Signature' => $validSignature
        ], $jsonPayload);
        
        $response->assertStatus(200); // Because 'ping' is ignored cleanly
    }

    public function test_channel_reservation_creates_pms_reservation()
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
            'hotel_identifier' => $this->hotel->id,
            'event_type' => 'reservation',
            'channel_reservation_id' => 'BKG-999',
            'room_identifier' => 'BKG-RM-1',
            'check_in_date' => now()->addDays(2)->toDateString(),
            'check_out_date' => now()->addDays(5)->toDateString(),
            'adults' => 2,
            'guest' => [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.ota@example.com'
            ]
        ];

        $response = $this->postJson("/api/v1/channels/webhook/booking.com", $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('reservations', [
            'source' => 'ota',
            'hotel_id' => $this->hotel->id,
        ]);

        $res = Reservation::where('source', 'ota')->first();

        $this->assertDatabaseHas('channel_reservations', [
            'channel_integration_id' => $integration->id,
            'channel_reservation_id' => 'BKG-999',
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
            'hotel_identifier' => $this->hotel->id,
            'event_type' => 'reservation',
            'channel_reservation_id' => 'BKG-DUPLICATE',
            'room_identifier' => 'BKG-RM-1',
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(),
            'guest' => ['first_name' => 'A', 'last_name' => 'B']
        ];

        // First ingestion
        $response1 = $this->postJson("/api/v1/channels/webhook/booking.com", $payload);
        $response1->assertStatus(201);

        // Second ingestion (duplicate)
        $response2 = $this->postJson("/api/v1/channels/webhook/booking.com", $payload);
        $response2->assertStatus(200); // Clean exit, ignored
        $response2->assertJson(['status' => 'ignored']);

        // Should only be one 
        $count = ChannelReservation::where('channel_reservation_id', 'BKG-DUPLICATE')->count();
        $this->assertEquals(1, $count);
    }

    public function test_channel_sync_toggle_disabled()
    {
        $integration = ChannelIntegration::create([
            'hotel_id' => $this->hotel->id,
            'channel_name' => 'expedia',
            'sync_enabled' => false, // Fully disabled via GUI
        ]);

        $payload = [
            'hotel_identifier' => $this->hotel->id,
            'event_type' => 'reservation',
        ];

        $response = $this->postJson("/api/v1/channels/webhook/expedia", $payload);
        $response->assertStatus(200);
        $response->assertJson(['reason' => 'Sync disabled']);
    }
}
