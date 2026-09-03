<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\Reservation;
use App\Models\RevenueInsight;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\RevenueIntelligenceService;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RevenueIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;
    protected User $admin;
    protected RevenueIntelligenceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(RevenueIntelligenceService::class);

        // Seed plans & active subscription for 402 bypass
        $this->artisan('db:seed --class=SubscriptionPlanSeeder');
        
        $this->hotel = Hotel::factory()->create();
        $this->admin = User::factory()->create(['hotel_id' => $this->hotel->id]);
        
        $plan = SubscriptionPlan::first();
        if ($plan) {
            app(SubscriptionService::class)->createSubscription($this->hotel, $plan);
        }

        // Create some rooms
        $roomType = RoomType::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Standard Room',
            'base_price' => 50000,
            'capacity' => 2
        ]);

        for ($i = 101; $i <= 110; $i++) {
            Room::create([
                'hotel_id' => $this->hotel->id,
                'room_type_id' => $roomType->id,
                'room_number' => (string)$i,
                'status' => 'available',
                'floor' => 1
            ]);
        }
    }

    #[Test]
    public function test_occupancy_trend_calculation(): void
    {
        $this->actingAs($this->admin);
        session(['hotel_id' => $this->hotel->id]);
        
        // 5 rooms out of 10 occupied = 50% occupancy
        $this->createReservationsOnDate(Carbon::today(), 5);
        
        // Debug check
        // fwrite(STDERR, "RES COUNT: " . Reservation::count() . "\n");
        // fwrite(STDERR, "JSON: " . Reservation::first()?->toJson() . "\n");

        $insights = $this->service->generateInsights($this->hotel, Carbon::today(), Carbon::today());
        $insight = $insights->first();

        $this->assertEquals(50.00, (float)$insight->occupancy_rate);
    }

    #[Test]
    public function test_revenue_prediction_and_demand_score(): void
    {
        // High occupancy (9 of 10 rooms) should trigger high demand score
        $this->createReservationsOnDate(Carbon::today(), 9);

        $insights = $this->service->generateInsights($this->hotel, Carbon::today(), Carbon::today());
        $insight = $insights->first();

        // High occupancy should lead to high demand score (min 90 in our logic)
        $this->assertGreaterThanOrEqual(90, $insight->demand_score);
    }

    #[Test]
    public function test_pricing_recommendation_generation(): void
    {
        // 100% occupancy
        $this->createReservationsOnDate(Carbon::today(), 10);

        $insights = $this->service->generateInsights($this->hotel, Carbon::today(), Carbon::today());
        $insight = $insights->first();

        $recommendations = $insight->recommended_rate_adjustment;
        $this->assertNotEmpty($recommendations);
        
        // Should suggest an increase (15% base + simulated variance)
        $this->assertGreaterThanOrEqual(13, $recommendations[0]['adjustment_percent']);
        $this->assertGreaterThan(50000, $recommendations[0]['suggested_rate']);
    }

    #[Test]
    public function test_api_endpoints_return_insights(): void
    {
        $this->actingAs($this->admin);
        $response = $this->getJson('/api/v1/admin/revenue/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'today',
                    'forecast',
                    'recommendations',
                    'config'
                ]
            ]);
    }

    #[Test]
    public function test_event_impact_on_demand_score(): void
    {
        $this->actingAs($this->admin);
        
        // Base occupancy 10%
        $this->createReservationsOnDate(Carbon::today(), 1);
        
        // Add a critical event (+25)
        \App\Models\HotelEvent::create([
            'hotel_id' => $this->hotel->id,
            'name' => 'Grand Tech Summit',
            'start_date' => Carbon::today(),
            'end_date' => Carbon::today(),
            'impact_level' => 'critical'
        ]);

        $insights = $this->service->generateInsights($this->hotel, Carbon::today(), Carbon::today());
        $insight = $insights->first();

        // 10 (occ) + 25 (event) + 5 (weekday trend) = approx 40
        $this->assertGreaterThan(30, $insight->demand_score);
    }

    protected function createReservationsOnDate(Carbon $date, int $count): void
    {
        $rooms = $this->hotel->rooms()->limit($count)->get();
        $guest = \App\Models\Guest::create([
            'hotel_id' => $this->hotel->id,
            'first_name' => 'Test',
            'last_name' => 'Guest',
            'email' => 'test@example.com',
            'phone' => '1234567890'
        ]);

        foreach ($rooms as $room) {
            $reservation = Reservation::create([
                'hotel_id' => $this->hotel->id,
                'guest_id' => $guest->id,
                'reservation_number' => 'RSV-' . uniqid(),
                'check_in_date' => $date->toDateString(),
                'check_out_date' => $date->copy()->addDay()->toDateString(),
                'status' => 'confirmed',
                'total_amount' => 50000
            ]);
            
            $reservation->rooms()->attach($room->id, ['rate' => 50000]);
        }
    }
}
