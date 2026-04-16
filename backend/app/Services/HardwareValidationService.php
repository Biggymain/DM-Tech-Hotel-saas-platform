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
        \Illuminate\Support\Facades\Log::debug("HardwareValidationService: Validating hash: {$hash}");
        $cacheKey = "licensing_sentry_{$hash}";
        $device = Cache::get($cacheKey);

        if (!$device) {
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
                Cache::put($cacheKey, $device, now()->addMinutes(15));
            }
        }

        return $device;
    }
}
