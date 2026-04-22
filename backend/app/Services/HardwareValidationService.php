<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HardwareValidationService
{
    /**
     * Validate a hardware hash against the registered devices in Supabase.
     *
     * @param string $hash
     * @return array|null Returns licensing data if valid, null otherwise.
     */
    public function validate(string $hash): ?array
    {
        $cacheKey = "licensing_sentry_{$hash}";
        $device = Cache::get($cacheKey);

        if (!$device) {
            // 1. Local Bypass Logic (Local Development Only) - Phoenix Master Marriage
            if (app()->isLocal() || app()->runningUnitTests()) {
                $localDevice = DB::table('hardware_devices')
                    ->where('hardware_hash', $hash)
                    ->where('status', 'active')
                    ->first();

                if ($localDevice) {
                    $device = [
                        'hotel_id' => $localDevice->hotel_id,
                        'is_manually_locked' => 0,
                        'expires_at' => now()->addYears(10)->toDateTimeString(),
                        'device_active' => 1,
                        'access_level' => $localDevice->access_level ?? 'terminal',
                        'manager_email' => 'dev@dmtech.local',
                        'owner_email' => 'dev@dmtech.local',
                    ];
                    Cache::put($cacheKey, $device, now()->addMinutes(15));
                    return $device;
                }
            }

            // 2. Standard Global Sentry (Supabase)
            try {
                $deviceRaw = DB::connection('supabase')->table('devices')
                    ->join('branches', 'devices.branch_id', '=', 'branches.id')
                    ->where('devices.hardware_hash', $hash)
                    ->select([
                        'devices.is_manually_locked',
                        'devices.expires_at',
                        'branches.is_active as device_active',
                        'branches.manager_email',
                        'branches.owner_email'
                    ])
                    ->first();

                if ($deviceRaw) {
                    $device = (array) $deviceRaw;
                    $device['access_level'] = 'terminal'; // Default for production registered devices
                    Cache::put($cacheKey, $device, now()->addMinutes(15));
                }
            } catch (\Exception $e) {
                // Fail-safe: if Supabase connection fails, do not allow access (unless local bypass hit)
                return null;
            }
        }

        return $device;
    }
}
