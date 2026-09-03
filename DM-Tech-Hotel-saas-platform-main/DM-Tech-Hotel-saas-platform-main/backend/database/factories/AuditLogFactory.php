<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => null,
            'user_id' => null,
            'entity_type' => 'user',
            'entity_id' => 1,
            'change_type' => 'test_event',
            'old_values' => [],
            'new_values' => [],
            'reason' => 'Testing watchdog correlation',
            'source' => 'api',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0 (Mock)',
        ];
    }
}
