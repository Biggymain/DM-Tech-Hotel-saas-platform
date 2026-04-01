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

    protected function setUp(): void
    {
        parent::setUp();
        $this->hotel = Hotel::factory()->create();
        $this->user = User::factory()->create([
            'hotel_id' => $this->hotel->id,
            'email' => 'admin@hotel.com',
            'password' => Hash::make('password123'),
            'password_changed_at' => now(),
        ]);
    }

    public function test_user_can_login_with_correct_credentials()
    {
        Event::fake([Login::class]);

        $response = $this->postJson('/api/v1/auth/login', [
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
            ->assertJson(['message' => 'Password reset link sent to your email.']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin@hotel.com',
        ]);
    }

    public function test_user_can_reset_password_with_valid_token()
    {
        $token = 'test-token';
        DB::table('password_reset_tokens')->insert([
            'email' => 'admin@hotel.com',
            'token' => Hash::make($token),
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => 'admin@hotel.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson(['message' => 'Password has been reset successfully.']);

        $this->assertTrue(Hash::check('newpassword123', $this->user->refresh()->password));
        
        // Assert Audit Log
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->user->id,
            'change_type' => 'password_reset',
        ]);
    }

    public function test_reset_password_fails_with_invalid_token()
    {
        $response = $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => 'admin@hotel.com',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired token.']);
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
}
