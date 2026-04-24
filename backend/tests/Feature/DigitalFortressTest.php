<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\HotelWebsiteOverride;
use App\Models\User;
use App\Models\HardwareDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class DigitalFortressTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Setup a registered hardware device for the test
        $this->deviceHash = 'test-hardware-hash-' . uniqid();
        HardwareDevice::create([
            'hardware_uuid' => \Illuminate\Support\Str::uuid(),
            'hardware_hash' => $this->deviceHash,
            'device_name' => 'Test Terminal',
            'device_active' => true,
            'expires_at' => now()->addYear(),
            'hotel_id' => null, // Master or global device
            'access_level' => 'master'
        ]);
    }

    /** @test */
    public function it_blocks_web_routes_on_port_3005_when_internal_website_is_disabled()
    {
        $hotel = Hotel::factory()->create();
        
        // Define a temporary route for testing that uses the SentryMiddleware
        \Route::middleware([\App\Http\Middleware\SentryMiddleware::class])
            ->get('/test-booking-portal', function() {
                return response('OK');
            });

        // Create settings with toggle OFF
        HotelSetting::create([
            'hotel_id' => $hotel->id,
            'setting_key' => 'general',
            'use_internal_website' => false
        ]);

        // Mock the tenant_id in the application
        app()->instance('tenant_id', $hotel->id);

        $response = $this->withHeaders([
            'X-Frontend-Port' => '3005',
            'X-Hardware-Id' => $this->deviceHash,
        ])->get('/test-booking-portal');

        $response->assertStatus(503);
        $response->assertJsonFragment(['error' => 'Maintenance Mode']);
    }

    /** @test */
    public function it_allows_api_routes_on_port_3005_even_when_internal_website_is_disabled()
    {
        $hotel = Hotel::factory()->create();
        
        HotelSetting::create([
            'hotel_id' => $hotel->id,
            'setting_key' => 'general',
            'use_internal_website' => false
        ]);

        // API route should be allowed (we'll use a known API route or mock it)
        $response = $this->withHeaders([
            'X-Frontend-Port' => '3005',
            'X-Hardware-Id' => $this->deviceHash,
            'Accept' => 'application/json',
        ])->get('/api/v1/theme');

        // Status might be 200 or 401 depending on auth, but it shouldn't be 503 Maintenance
        $this->assertNotEquals(503, $response->getStatusCode());
    }

    /** @test */
    public function it_can_generate_and_encrypt_ota_token()
    {
        $hotel = Hotel::factory()->create();
        
        $token = $hotel->generateOtaIntegrationKey();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token));
        
        // Verify it's stored encrypted in the DB
        $rawHotel = \DB::table('hotels')->where('id', $hotel->id)->first();
        $this->assertNotEquals($token, $rawHotel->ota_token);
        
        // Verify model decrypts it automatically
        $hotel->refresh();
        $this->assertEquals($token, $hotel->ota_token);
    }

    /** @test */
    public function it_supports_template_id_in_website_overrides()
    {
        $hotel = Hotel::factory()->create();
        
        $override = HotelWebsiteOverride::create([
            'hotel_id' => $hotel->id,
            'template_id' => 3,
            'custom_title' => 'Fortress Hotel'
        ]);

        $this->assertEquals(3, $override->template_id);
        
        $hotel->refresh();
        $this->assertEquals(3, $hotel->websiteOverride->template_id);
    }
}
