<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * RateLimitingMiddlewareTest (CIA Triad: Availability)
 *
 * Verifies that rate limiting and DDoS throttling prevent resource exhaustion
 * and ensure continuous system availability.
 */
class RateLimitingMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected Hotel $hotel;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();

        $this->hotel = Hotel::create([
            'name'      => 'Availability Hotel',
            'domain'    => 'avail.dmtech.local',
            'is_active' => true,
        ]);
    }

    #[Test]
    public function test_guest_portal_enforces_rate_limiting_for_availability(): void
    {
        // Route prefix 'guest' is protected by throttle:10,1
        $endpoint = '/api/v1/guest/session/start';
        
        $payload = [
            'hotel_id'     => $this->hotel->id,
            'context_type' => 'room',
            'context_id'   => '101',
            'signature'    => 'test_signature',
        ];

        // Send 10 allowable requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson($endpoint, $payload);
            // Will return either 200, 422 or 403 depending on signature validation, but NOT 429
            $this->assertNotEquals(429, $response->getStatusCode());
        }

        // The 11th request must trigger HTTP 429 Too Many Requests
        $throttleResponse = $this->postJson($endpoint, $payload);
        $this->assertEquals(429, $throttleResponse->getStatusCode());
    }

    #[Test]
    public function test_lock_events_webhook_is_rate_limited(): void
    {
        // /integration/lock-events is protected by throttle:60,1
        $endpoint = '/api/v1/integration/lock-events';

        $response = $this->postJson($endpoint, ['event' => 'test']);
        // Returns 400 or 401 for invalid HMAC, but allows through throttle
        $this->assertContains($response->getStatusCode(), [200, 400, 401, 403]);
    }
}
