<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Auth\Events\Login;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;
    protected $hotel;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create();
        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'email' => 'admin@hotel.com',
            'password' => 'password123',
            'password_changed_at' => now(),
            'hardware_hash' => 'valid-hardware-hash', // satisfy SentryMiddleware requirement
            'is_approved' => true,
        ]);

        // Seed roles for testing
        \App\Models\Role::create(['name' => 'General Manager', 'slug' => 'generalmanager']);
        \App\Models\Role::create(['name' => 'Super Admin', 'slug' => 'superadmin']);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        Event::fake([Login::class]);
        
        $managerRole = \App\Models\Role::withoutGlobalScopes()->where('slug', 'generalmanager')->first();
        $this->user->roles()->attach($managerRole->id, ['hotel_id' => $this->hotel->id]);

        $response = $this->withHeaders(['X-Hardware-Id' => 'valid-hardware-hash'])
            ->withPort(3002)
            ->postJson('/api/v1/auth/login', [
            'email' => 'admin@hotel.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['token', 'user']);

        Event::assertDispatched(Login::class);
    }

    public function test_user_cannot_login_with_incorrect_password()
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@hotel.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_request_password_reset_link()
    {
        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin@hotel.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'A 6-digit password reset code has been sent to your email.']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin@hotel.com',
        ]);
    }

    public function test_user_can_reset_password_with_valid_token()
    {
        $otp = '123456';
        DB::table('password_reset_tokens')->insert([
            'email' => 'admin@hotel.com',
            'token' => $otp,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $otp,
            'email' => 'admin@hotel.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password has been reset successfully.']);

        $this->assertEquals('newpassword123', $this->user->refresh()->password);
        
        // Assert Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'change_type' => 'password_reset',
        ]);
    }

    public function test_reset_password_fails_with_invalid_token()
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => '000000',
            'email' => 'admin@hotel.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired reset code.']);
    }

    public function test_login_alert_logs_activity()
    {
        $listener = new \App\Listeners\SendLoginAlert();
        $event = new Login('sanctum', $this->user, false);

        $listener->handle($event);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'change_type' => 'login',
        ]);
    }

    /**
     * Port Enforcement: Ghosting (404 Not Found)
     */
    public function test_admin_access_on_wrong_port_ghosts()
    {
        $role = \App\Models\Role::where('slug', 'superadmin')->first();
        $this->user->roles()->attach($role->id);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Frontend-Port', '3005') // Wrong port for SuperAdmin (should be 3000)
            ->getJson('/api/v1/profile');

        $response->assertStatus(404);
    }
}
