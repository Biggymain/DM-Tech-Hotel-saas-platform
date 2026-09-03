<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use App\Models\Hotel;
use App\Services\ModuleService;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class OfflineModuleTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_offline_mode_reads_from_json_snapshot_securely()
    {
        $hotel = Hotel::create(['name' => 'Offline Sim Hotel', 'domain' => 'offline.test']);
        $branchId = 999;

        // Manually place the local JSON fallback
        Storage::disk('local')->put("offline_snapshots/modules_{$hotel->id}_{$branchId}.json", json_encode([
            'modules' => ['pos', 'kitchen_display']
        ]));

        Cache::flush();

        // 1. Force the database connector to structurally throw a connection failure via MySQL QueryException
        DB::shouldReceive('table')
            ->atLeast()->once()
            ->andThrow(new QueryException((new \Illuminate\Database\Connection('mysql'))->getName(), 'select * from hotel_modules', [], new \Exception('Simulated Connection Pool Drop')));

        $service = app(ModuleService::class);

        // 2. The resolution must gracefully trap the Exception and leverage SQLite/JSON Storage exactly mapping to the explicit files without 500 erroring out
        $this->assertTrue($service->isEnabled('pos', $hotel->id, $branchId));
        $this->assertFalse($service->isEnabled('reception', $hotel->id, $branchId));
    }

    #[Test]
    public function test_disabled_module_blocks_endpoint_with_safe_403()
    {
        $hotel = Hotel::create(['name' => 'Test Hotel', 'domain' => 'test.com']);
        $user = \App\Models\User::factory()->create(['hotel_id' => $hotel->id]);
        
        $role = \App\Models\Role::create(['hotel_id' => $hotel->id, 'name' => 'Kitchen Staff', 'slug' => 'kitchen']);
        $permission = \App\Models\Permission::create(['name' => 'View KDS', 'slug' => 'kds.view']);
        
        $role->permissions()->attach($permission->id, ['hotel_id' => $hotel->id]);
        $user->roles()->attach($role->id, ['hotel_id' => $hotel->id]);

        $response = $this->actingAs($user)->withHeader('X-Test-Enforce-Module-Limits', '1')->getJson('/api/v1/kds/tickets');

        $response->assertStatus(403)
                 ->assertJson([
                     'status' => 'error',
                     'message' => 'Module access unavailable in current mode'
                 ]);
    }
}
