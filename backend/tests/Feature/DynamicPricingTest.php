<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Hotel;
use App\Models\RoomType;
use App\Models\Room;
use App\Models\Guest;
use App\Models\RatePlan;
use App\Models\SeasonalRate;
use App\Models\OccupancyRateRule;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use App\Services\PricingService;

class DynamicPricingTest extends TestCase
{
    use RefreshDatabase;

    protected $hotel;
    protected $user;
    protected $roomType;
    protected $room;
    protected $guest;

    protected function setUp(): void
    {
        parent::setUp();
        
        Hotel::unguard();
        User::unguard();
        Role::unguard();
        Room::unguard();

        $this->hotel = Hotel::create([
            'name' => 'Grand Hotel', 
            'email' => 'contact@grandhotel.com',
            'is_active' => true
        ]);
        $this->user = User::factory()->create(['hotel_id' => $this->hotel->id]);
        
        $role = Role::create(['name' => 'Manager', 'slug' => 'manager', 'hotel_id' => $this->hotel->id]);
        \App\Models\Permission::firstOrCreate(['name' => 'pricing.rate_plans.manage', 'slug' => 'pricing.rate_plans.manage']);
        \App\Models\Permission::firstOrCreate(['name' => 'pricing.rate_plans.view', 'slug' => 'pricing.rate_plans.view']);
        \App\Models\Permission::firstOrCreate(['name' => 'reservations.create', 'slug' => 'reservations.create']);
        $role->permissions()->attach(Permission::pluck('id'));
        $this->user->roles()->attach($role->id);

        $this->roomType = RoomType::create(['hotel_id' => $this->hotel->id, 'name' => 'Deluxe Suite', 'base_price' => 200, 'capacity' => 2]);
        $this->room = Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->roomType->id, 'room_number' => '101']);
        $this->guest = Guest::create(['hotel_id' => $this->hotel->id, 'first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@ex.com', 'phone' => '12345']);

        app()->instance('tenant_id', $this->hotel->id);
    }

    public function test_rate_plan_creation()
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/pricing/rate-plans', [
            'name' => 'Summer Special',
            'pricing_strategy' => 'seasonal',
            'is_active' => true,
            'base_price_modifier' => 50,
            'room_types' => [
                ['id' => $this->roomType->id, 'base_price' => 250]
            ]
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('rate_plans', ['name' => 'Summer Special', 'base_price_modifier' => 50]);
        $this->assertDatabaseHas('room_type_rate_plan', ['room_type_id' => $this->roomType->id, 'base_price' => 250]);
    }

    public function test_rate_plan_validity_window()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Flash Sale',
            'pricing_strategy' => 'fixed',
            'base_price_modifier' => -50,
            'is_active' => true,
            'valid_from' => now()->addDays(1),
            'valid_until' => now()->addDays(5)
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        $pricingService = app(PricingService::class);
        
        // Date before validity window -> should fall back to base room price (200)
        $priceBefore = $pricingService->calculateRoomPrice($this->roomType, now());
        $this->assertEquals(200, $priceBefore);

        // Date inside validity window -> should apply modifier (-50)
        $priceInside = $pricingService->calculateRoomPrice($this->roomType, now()->addDays(2), $ratePlan);
        $this->assertEquals(150, $priceInside);
    }

    public function test_seasonal_price_modifier()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Winter Promo',
            'pricing_strategy' => 'seasonal',
            'base_price_modifier' => 0,
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        SeasonalRate::create([
            'hotel_id' => $this->hotel->id,
            'rate_plan_id' => $ratePlan->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
            'price_modifier' => 100, // +100 during this period
        ]);

        $pricingService = app(PricingService::class);
        
        $price = $pricingService->calculateRoomPrice($this->roomType, now()->addDays(15), $ratePlan);
        $this->assertEquals(300, $price); // 200 + 100
    }

    public function test_weekend_seasonal_pricing()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Weekend Gateway',
            'pricing_strategy' => 'seasonal',
            'base_price_modifier' => 0,
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        SeasonalRate::create([
            'hotel_id' => $this->hotel->id,
            'rate_plan_id' => $ratePlan->id,
            'start_date' => now()->subDays(10),
            'end_date' => now()->addDays(30),
            'price_modifier' => 50,
            'days_of_week' => ['fri', 'sat']
        ]);

        $pricingService = app(PricingService::class);
        
        // Find next Friday
        $friday = now()->next(\Carbon\CarbonInterface::FRIDAY);
        // Find next Monday
        $monday = now()->next(\Carbon\CarbonInterface::MONDAY);

        $priceFriday = $pricingService->calculateRoomPrice($this->roomType, $friday, $ratePlan);
        $this->assertEquals(250, $priceFriday);

        $priceMonday = $pricingService->calculateRoomPrice($this->roomType, $monday, $ratePlan);
        $this->assertEquals(200, $priceMonday);
    }

    public function test_occupancy_pricing_rule()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'High Demand',
            'pricing_strategy' => 'occupancy',
            'base_price_modifier' => 0,
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        // Create 3 more rooms (total 4)
        Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->roomType->id, 'room_number' => '102', 'status' => 'occupied']);
        Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->roomType->id, 'room_number' => '103', 'status' => 'occupied']);
        Room::create(['hotel_id' => $this->hotel->id, 'room_type_id' => $this->roomType->id, 'room_number' => '104', 'status' => 'available']);
        // The first room 101 is available. Total occupied = 2/4 = 50%

        OccupancyRateRule::create([
            'hotel_id' => $this->hotel->id,
            'rate_plan_id' => $ratePlan->id,
            'occupancy_threshold' => 50,
            'price_modifier_percentage' => 25 // +25% when occupancy >= 50%
        ]);

        $pricingService = app(PricingService::class);
        $price = $pricingService->calculateRoomPrice($this->roomType, now(), $ratePlan);

        $this->assertEquals(250, $price); // 200 + (200 * 0.25)
    }

    public function test_price_guard_limits()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Guarded Price',
            'pricing_strategy' => 'seasonal',
            'base_price_modifier' => 500, // Makes price 700
            'is_active' => true,
            'max_price' => 500 // Limit at 500
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        $pricingService = app(PricingService::class);
        $price = $pricingService->calculateRoomPrice($this->roomType, now(), $ratePlan);

        $this->assertEquals(500, $price); // Clamped to max_price
    }

    public function test_pricing_cache_returns_same_value()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Cache Test',
            'pricing_strategy' => 'fixed',
            'base_price_modifier' => 0,
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        $pricingService = app(PricingService::class);
        $date = now();

        $price1 = $pricingService->calculateRoomPrice($this->roomType, $date, $ratePlan);
        $this->assertEquals(200, $price1);

        // Modify rate plan directly in DB to bypass models/events cache clear if any
        DB::table('room_type_rate_plan')->update(['base_price' => 500]);

        // Should return 200 instead of 500 due to cache
        $price2 = $pricingService->calculateRoomPrice($this->roomType, $date, $ratePlan);
        $this->assertEquals(200, $price2);
    }

    public function test_reservation_uses_dynamic_price()
    {
        $ratePlan = RatePlan::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Reservation Test Plan',
            'pricing_strategy' => 'fixed',
            'base_price_modifier' => 100, // 200 + 100 = 300
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($this->roomType->id, ['base_price' => 200, 'hotel_id' => $this->hotel->id]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/pms/reservations', [
            'hotel_id' => $this->hotel->id,
            'guest_id' => $this->guest->id,
            'check_in_date' => now()->addDays(1)->toDateString(),
            'check_out_date' => now()->addDays(3)->toDateString(), // 2 nights
            'source' => 'website',
            'adults' => 2,
            'rate_plan_id' => $ratePlan->id,
            'rooms' => [
                ['id' => $this->room->id] // Will fetch dynamic price automatically if no rate provided
            ]
        ]);

        $response->assertStatus(201);
        $reservation = $response->json('data');
        
        // 300/night * 2 nights = 600
        $this->assertEquals(600, $reservation['total_amount']);
        $this->assertEquals(600, $reservation['locked_price']);
    }

    public function test_tenant_isolation_between_hotels()
    {
        $hotelB = Hotel::create([
            'name' => 'Hotel B', 
            'email' => 'b@hotel.com',
            'is_active' => true
        ]);
        $roomTypeB = RoomType::create(['hotel_id' => $hotelB->id, 'name' => 'Standard', 'base_price' => 100]);
        
        $ratePlan = RatePlan::create([
            'hotel_id' => $hotelB->id,
            'name' => 'B Plan',
            'pricing_strategy' => 'fixed',
            'base_price_modifier' => 0,
            'is_active' => true,
        ]);
        $ratePlan->roomTypes()->attach($roomTypeB->id, ['base_price' => 100, 'hotel_id' => $hotelB->id]);

        // Access via hotel A's user should fail
        $response = $this->actingAs($this->user)->getJson('/api/v1/pricing/rate-plans');
        $response->assertStatus(200);
        $this->assertCount(0, $response->json()); // Doesn't see B's plan
    }
}
