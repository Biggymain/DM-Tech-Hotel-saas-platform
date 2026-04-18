<?php

namespace Tests\Feature;

use App\Models\Hotel;
use App\Models\User;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class NotificationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'RoleAndPermissionSeeder']);
    }

    #[Test]
    public function test_user_can_fetch_own_tenant_notifications()
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'domain' => 'test', 'is_active' => true]);
        $user = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        // Target user notification
        Notification::create([
            'hotel_id' => $hotel->id,
            'user_id' => $user->id,
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'type' => 'alert',
            'title' => 'Personal Alert',
            'message' => 'This is for you'
        ]);

        // Global tenant notification
        Notification::create([
            'hotel_id' => $hotel->id,
            'notifiable_id' => $hotel->id,
            'notifiable_type' => Hotel::class,
            'type' => 'broadcast',
            'title' => 'Global Alert',
            'message' => 'This is for everyone in hotel'
        ]);

        // Foreign hotel notification
        $foreignHotel = Hotel::create(['name' => 'Foreign', 'domain' => 'foreign', 'is_active' => true]);
        Notification::create([
            'hotel_id' => $foreignHotel->id,
            'notifiable_id' => $foreignHotel->id,
            'notifiable_type' => Hotel::class,
            'type' => 'broadcast',
            'title' => 'Foreign Alert',
            'message' => 'Should not see this'
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.data')
            ->assertJsonFragment(['title' => 'Personal Alert'])
            ->assertJsonFragment(['title' => 'Global Alert'])
            ->assertJsonMissing(['title' => 'Foreign Alert']);
    }

    #[Test]
    public function test_user_can_mark_notification_as_read()
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'domain' => 'test', 'is_active' => true]);
        $user = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        $notification = Notification::create([
            'hotel_id' => $hotel->id,
            'user_id' => $user->id,
            'notifiable_id' => $user->id,
            'notifiable_type' => User::class,
            'type' => 'alert',
            'title' => 'Test',
            'message' => 'Test message'
        ]);

        $response = $this->actingAs($user)->putJson("/api/v1/notifications/{$notification->id}/read");

        $response->assertStatus(200);
        $this->assertTrue($notification->fresh()->is_read);
    }

    #[Test]
    public function test_user_can_bulk_read_notifications()
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'domain' => 'test', 'is_active' => true]);
        $user = User::factory()->create(['hotel_id' => $hotel->id, 'is_super_admin' => true]);

        Notification::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'notifiable_id' => $user->id, 'notifiable_type' => User::class, 'type' => 'alert', 'title' => 'A', 'message' => 'A']);
        Notification::create(['hotel_id' => $hotel->id, 'user_id' => $user->id, 'notifiable_id' => $user->id, 'notifiable_type' => User::class, 'type' => 'alert', 'title' => 'B', 'message' => 'B']);

        $response = $this->actingAs($user)->putJson("/api/v1/notifications/read-all");

        $response->assertStatus(200);
        $this->assertEquals(2, Notification::where('is_read', true)->count());
    }

    #[Test]
    public function test_clean_expired_notifications_job_removes_old_data()
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'domain' => 'test', 'is_active' => true]);

        // Expired notification
        Notification::create([
            'hotel_id' => $hotel->id,
            'notifiable_id' => $hotel->id,
            'notifiable_type' => Hotel::class,
            'type' => 'alert',
            'title' => 'Expired',
            'message' => 'Should delete',
            'expires_at' => Carbon::now()->subDay()
        ]);

        // Old Read Notification (>30 days)
        $oldRead = Notification::create([
            'hotel_id' => $hotel->id,
            'notifiable_id' => $hotel->id,
            'notifiable_type' => Hotel::class,
            'type' => 'alert',
            'title' => 'Old Read',
            'message' => 'Should delete',
            'is_read' => true,
        ]);
        // Manually update timestamps to bypass Eloquent auto-updates
        \DB::table('notifications')->where('id', $oldRead->id)->update(['created_at' => Carbon::now()->subDays(31)]);

        // Valid unread notification
        Notification::create([
            'hotel_id' => $hotel->id,
            'notifiable_id' => $hotel->id,
            'notifiable_type' => Hotel::class,
            'type' => 'alert',
            'title' => 'Valid',
            'message' => 'Keep me',
            'expires_at' => Carbon::now()->addDays(5)
        ]);

        $service = new NotificationService();
        $service->clearExpired();

        $this->assertEquals(1, Notification::count());
        $this->assertEquals('Valid', Notification::first()->title);
    }
}
