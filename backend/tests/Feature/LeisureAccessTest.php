<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Outlet;
use App\Models\User;
use App\Models\Membership;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\LeisureAccessLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeisureAccessTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $outlet;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create();
        $this->outlet = Outlet::factory()->create(['hotel_id' => $this->hotel->id]);
        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'is_approved' => true,
            'is_super_admin' => true,
        ]);
        $this->actingAs($this->user);
    }

    public function test_hardware_verify_with_membership()
    {
        // 1. Create User & Membership
        $guest = User::factory()->create(['hotel_id' => $this->hotel->id]);
        Membership::create([
            'user_id' => $guest->id,
            'hotel_id' => $this->hotel->id,
            'type' => 'monthly',
            'price_paid' => 100,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addMonth(),
            'status' => 'active'
        ]);

        // 2. Call Hardware Verify
        $response = $this->getJson("/api/v1/hardware/verify/{$guest->id}?outlet_id={$this->outlet->id}");

        $response->assertStatus(200)
            ->assertJson(['allow' => true, 'type' => 'membership']);
        
        $this->assertDatabaseHas('leisure_access_logs', [
            'user_id' => $guest->id,
            'allow' => true,
            'method' => 'MEMBERSHIP'
        ]);
    }

    public function test_hardware_verify_with_paid_pass()
    {
        $guest = User::factory()->create(['hotel_id' => $this->hotel->id]);
        $order = Order::factory()->create([
            'hotel_id' => $this->hotel->id,
            'outlet_id' => $this->outlet->id,
            'created_by' => $guest->id,
            'order_status' => 'served',
            'order_number' => 'PASS-402'
        ]);

        $response = $this->getJson("/api/v1/hardware/verify/PASS-402?outlet_id={$this->outlet->id}");

        $response->assertStatus(200)
            ->assertJson(['allow' => true, 'type' => 'pass']);
    }

    public function test_hardware_verify_denied()
    {
        $guest = User::factory()->create();
        $response = $this->getJson("/api/v1/hardware/verify/{$guest->id}?outlet_id={$this->outlet->id}");

        $response->assertStatus(403)
            ->assertJson(['allow' => false, 'message' => 'No active membership or pass']);
    }

    public function test_leisure_provisioning()
    {
        $response = $this->postJson("/api/v1/admin/leisure/provision", [
            'outlet_id' => $this->outlet->id,
            'hardware_bridge_id' => 'HUB-POOL-001',
            'inventory_source_outlet_id' => $this->outlet->id,
            'supervisor_id' => $this->user->id
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($this->outlet->fresh()->metadata['hardware_bridge_id']);
    }
}
