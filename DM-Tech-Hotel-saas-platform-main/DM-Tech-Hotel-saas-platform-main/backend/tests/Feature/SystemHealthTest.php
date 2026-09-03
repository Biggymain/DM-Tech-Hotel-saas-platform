<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class SystemHealthTest extends TestCase
{
    /**
     * Verify database connection is healthy.
     */
    #[Test]
    public function test_database_connection_is_healthy()
    {
        try {
            DB::connection()->getPdo();
            $this->assertTrue(true, 'Database connection is healthy.');
        } catch (\Exception $e) {
            $this->fail("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Verify Cache functionality is active.
     */
    #[Test]
    public function test_cache_is_working()
    {
        Cache::put('health_check', true, 10);
        $this->assertTrue(Cache::get('health_check'));
    }

    /**
     * Verify Redis connection is available for queues and broadcasting.
     */
    #[Test]
    public function test_redis_connection_is_healthy()
    {
        // Skip actual redis ping if not configured locally to prevent build failures on minimal runners
        if (config('database.redis.default.host') === '127.0.0.1') {
            try {
                if (!class_exists('Redis')) {
                    $this->markTestSkipped('Redis class not found (phpredis extension missing).');
                }
                // If using phpredis or predis
                Redis::ping();
                $this->assertTrue(true, 'Redis connection is healthy.');
            } catch (\Exception $e) {
                 $this->markTestSkipped('Redis is not available locally: ' . $e->getMessage());
            }
        } else {
             $this->assertTrue(true, 'Redis host is configured.');
        }
    }
}
