<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\MaintenanceRequest;
use App\Models\Role;
use Carbon\Carbon;

class MaintenanceWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected $manager;
    protected $maintenanceStaff;
    protected $hotel;
    protected $roomType;
    protected $room;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);

        // Add permissions directly for testing
        \App\Models\Permission::firstOrCreate(['name' => 'maintenance.requests.view', 'slug' => 'maintenance.requests.view']);
        \App\Models\Permission::firstOrCreate(['name' => 'maintenance.requests.manage', 'slug' => 'maintenance.requests.manage']);

        $this->hotel = Hotel::factory()->create();
        
        $managerRole = Role::where('name', 'Manager')->first();
        $this->manager = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->manager->roles()->attach($managerRole);

        $maintenanceRole = Role::firstOrCreate(['name' => 'Maintenance', 'slug' => 'maintenance', 'hotel_id' => $this->hotel->id]);
        $this->maintenanceStaff = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->maintenanceStaff->roles()->attach($maintenanceRole);

        $this->roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Standard Room',
            'base_price' => 100.00,
            'capacity' => 2
        ]);

        $this->room = Room::create([
            'hotel_id' => $this->hotel->id,
            'room_type_id' => $this->roomType->id,
            'room_number' => '101',
            'status' => 'available'
        ]);
    }

    public function test_can_create_maintenance_request()
    {
        \Illuminate\Support\Facades\Event::fake();
        $this->manager->update(['is_super_admin' => true]);

        $response = $this->actingAs($this->manager)->withHeaders([
            'X-Tenant-ID' => $this->hotel->id
        ])->postJson('/api/v1/maintenance/requests', [
            'room_id' => $this->room->id,
            'issue_type' => 'plumbing',
            'priority' => 'high',
            'description' => 'Leaking sink'
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('maintenance_requests', [
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'issue_type' => 'plumbing',
            'status' => 'open'
        ]);
    }

    public function test_starting_maintenance_blocks_room()
    {
        \Illuminate\Support\Facades\Event::fake();
        $this->maintenanceStaff->update(['is_super_admin' => true]);

        $request = MaintenanceRequest::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reported_by' => $this->manager->id,
            'issue_type' => 'plumbing',
            'description' => 'Fix pipe',
            'status' => 'assigned',
            'assigned_to' => $this->maintenanceStaff->id
        ]);

        $maintenanceUntil = Carbon::now()->addHours(2);

        $response = $this->actingAs($this->maintenanceStaff)->withHeaders([
            'X-Tenant-ID' => $this->hotel->id
        ])->postJson("/api/v1/maintenance/requests/{$request->id}/start", [
            'maintenance_until' => $maintenanceUntil->toDateTimeString()
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'status' => 'in_progress'
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'status' => 'maintenance',
            'maintenance_until' => $maintenanceUntil->toDateTimeString()
        ]);
    }

    public function test_resolving_maintenance_unblocks_room_and_sets_dirty()
    {
        \Illuminate\Support\Facades\Event::fake();
        $this->maintenanceStaff->update(['is_super_admin' => true]);

        $this->room->update(['status' => 'maintenance', 'maintenance_until' => Carbon::now()->addHour()]);

        $request = MaintenanceRequest::create([
            'hotel_id' => $this->hotel->id,
            'room_id' => $this->room->id,
            'reported_by' => $this->manager->id,
            'issue_type' => 'electrical',
            'description' => 'Fix lightbulb',
            'status' => 'in_progress',
            'assigned_to' => $this->maintenanceStaff->id
        ]);

        $response = $this->actingAs($this->maintenanceStaff)->withHeaders([
            'X-Tenant-ID' => $this->hotel->id
        ])->postJson("/api/v1/maintenance/requests/{$request->id}/resolve");

        $response->assertStatus(200);

        $this->assertDatabaseHas('maintenance_requests', [
            'id' => $request->id,
            'status' => 'resolved'
        ]);

        $this->assertDatabaseHas('rooms', [
            'id' => $this->room->id,
            'status' => 'available',
            'housekeeping_status' => 'dirty',
            'maintenance_until' => null
        ]);
    }
}
