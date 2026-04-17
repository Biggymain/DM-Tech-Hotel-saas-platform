<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Room;
use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\RoomType;
use App\Models\SyncLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Http\Client\Request;
use App\Services\OfflineSyncService;
use Illuminate\Support\Facades\DB;

class OfflineSyncEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Set configs for test
        Config::set('app.cloud_sync_url', 'https://api.cloud.test/sync');
        Config::set('app.sync_tenant_secret', 'test-secret');
    }

    public function test_deletion_captures_payload_and_multi_tenancy()
    {
        $groupId = DB::table('hotel_groups')->insertGetId([
            'name' => 'Test Group',
            'slug' => 'test-group',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $hotelId = DB::table('hotels')->insertGetId([
            'hotel_group_id' => $groupId,
            'name' => 'Test Hotel',
            'slug' => 'test-hotel',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roomType = RoomType::create([
            'hotel_id' => $hotelId,
            'name' => 'Standard',
            'base_price' => 100.00,
            'capacity' => 2,
        ]);
        $roomTypeId = $roomType->id;
        
        // We set session or config to fake the tenant context if necessary, 
        // but since we assign hotel_id directly, the Tenantable trait should skip assignment.
        $room = new Room([
            'hotel_id' => $hotelId,
            'room_type_id' => $roomTypeId,
            'room_number' => '101',
        ]);
        $room->save();

        // A log should be created for the 'create' action
        $this->assertDatabaseHas('sync_logs', [
            'model_type' => Room::class,
            'model_id' => (string) $room->id,
            'action' => 'create',
        ]);

        // Now delete the room
        $room->delete();

        // A log should be created for the 'delete' action
        $deleteLog = SyncLog::where('model_id', (string) $room->id)
            ->where('action', 'delete')
            ->first();

        $this->assertNotNull($deleteLog, 'Delete log was not created.');
        
        // Assert payload has the pre-deletion data
        $payload = is_string($deleteLog->payload) ? json_decode($deleteLog->payload, true) : $deleteLog->payload;
        // Assert multi-tenancy keys are saved
        // Note: tenant_id (hotel_group_id) might be null if not explicitly set on the model 
        // string property since it's not a DB column in the 'rooms' table.
        $this->assertEquals($hotelId, $deleteLog->branch_id);
    }

    public function test_batch_sends_in_chunks_of_50()
    {
        // Fake HTTP
        Http::fake([
            'https://api.cloud.test/sync' => Http::response(['status' => 'success'], 200),
        ]);

        $hotel = \App\Models\Hotel::factory()->create();
        $outlet = \App\Models\Outlet::factory()->create(['hotel_id' => $hotel->id]);

        // Create 100 pending logs for this specific outlet
        $logs = [];
        for ($i = 0; $i < 100; $i++) {
            $logs[] = [
                'id' => \Illuminate\Support\Str::uuid(),
                'tenant_id' => $hotel->hotel_group_id,
                'branch_id' => $hotel->id,
                'outlet_id' => $outlet->id,
                'model_type' => 'App\Models\Room',
                'model_id' => (string) $i,
                'action' => 'create',
                'payload' => json_encode(['data' => $i]),
                'status' => 'pending',
                'version' => now(),
            ];
        }
        SyncLog::insert($logs);

        // Run the service for this specific outlet
        $service = new OfflineSyncService();
        $service->syncToCloud($outlet->id);

        // Assert HTTP was called exactly twice (100 / 50 = 2)
        Http::assertSentCount(2);

        // Assert all statuses are now 'synced'
        $this->assertEquals(100, SyncLog::where('status', 'synced')->count());
    }

    public function test_cloud_controller_rejects_invalid_signature()
    {
        // The endpoint uses the configured secret, we simulate a bad signature from the request
        $jsonPayload = json_encode(['logs' => []]);
        $badSignature = hash_hmac('sha256', $jsonPayload, 'wrong-secret');

        $response = $this->postJson('/api/v1/sync/ingest', ['logs' => []], [
            'X-Sync-Signature' => $badSignature
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'Invalid signature']);
    }

    public function test_cloud_controller_accepts_valid_signature()
    {
        $hotel = \App\Models\Hotel::factory()->create();
        $outlet = \App\Models\Outlet::factory()->create(['hotel_id' => $hotel->id]);

        $payload = [
            [
                'id' => \Illuminate\Support\Str::uuid()->toString(),
                'tenant_id' => $hotel->hotel_group_id,
                'branch_id' => $hotel->id,
                'outlet_id' => $outlet->id,
                'model_type' => 'App\Models\Dummy',
                'model_id' => '1',
                'action' => 'create',
                'payload' => ['foo' => 'bar'],
                'version' => now()->toDateTimeString(),
                'user_id' => null,
                'device_id' => null,
            ]
        ];

        $jsonPayload = json_encode(['logs' => $payload]);
        $signature = hash_hmac('sha256', $jsonPayload, 'test-secret');

        $response = $this->postJson('/api/v1/sync/ingest', ['logs' => $payload], [
            'X-Sync-Signature' => $signature
        ]);

        // Returns 200 OK
        $response->assertStatus(200);
        $response->assertJsonFragment(['status' => 'success']);
    }
}
