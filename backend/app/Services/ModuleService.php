<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\QueryException;
use PDOException;

class ModuleService
{
    private const CACHE_TTL = 3600; // 1 hour (configurable)

    /**
     * Check if a module is enabled for a specific hotel (tenant) and optional branch (outlet).
     * Works seamlessly in Offline Mode via cached JSON snapshots.
     *
     * @param string $moduleSlug
     * @param int|null $hotelId
     * @param int|null $branchId
     * @return bool
     */
    public function isEnabled(string $moduleSlug, ?int $hotelId, ?int $branchId = null): bool
    {
        if (!$hotelId) return false;

        $cacheKey = "modules_{$hotelId}_{$branchId}";

        try {
            // Online flow: Check Cache, if miss, run DB query inside Closure
            return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($hotelId, $branchId) {
                return $this->fetchFromDatabase($hotelId, $branchId);
            })->contains($moduleSlug);
        } catch (QueryException | PDOException $e) {
            // Offline fall-back flow
            return $this->fetchFromOfflineSnapshot($hotelId, $branchId)->contains($moduleSlug);
        }
    }

    /**
     * Fetch active modules from MySQL (Online Mode)
     */
    private function fetchFromDatabase(int $hotelId, ?int $branchId): \Illuminate\Support\Collection
    {
        $query = DB::table('hotel_modules')
            ->join('modules', 'hotel_modules.module_id', '=', 'modules.id')
            ->where('hotel_modules.hotel_id', $hotelId)
            ->where('hotel_modules.is_enabled', true);

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                // Modules mapped explicitly to the branch, OR inherited from the tenant level
                $q->where('hotel_modules.branch_id', $branchId)
                  ->orWhereNull('hotel_modules.branch_id');
            });
        } else {
            // Strict tenant mapping
            $query->whereNull('hotel_modules.branch_id');
        }

        $modules = $query->pluck('modules.slug');

        // Automatically push an offline backup snapshot on successful DB reach
        $this->updateOfflineSnapshot($hotelId, $branchId, $modules->toArray());

        return $modules;
    }

    /**
     * Fetch from local JSON snapshot (Offline Mode)
     */
    private function fetchFromOfflineSnapshot(int $hotelId, ?int $branchId): \Illuminate\Support\Collection
    {
        $snapshotFile = "offline_snapshots/modules_{$hotelId}_{$branchId}.json";

        if (Storage::disk('local')->exists($snapshotFile)) {
            $data = json_decode(Storage::disk('local')->get($snapshotFile), true);
            return collect($data['modules'] ?? []);
        }

        // Failsafe: Deny access if offline and no snapshot exists
        return collect([]);
    }

    /**
     * Asynchronously keep offline snapshot updated
     */
    private function updateOfflineSnapshot(int $hotelId, ?int $branchId, array $modules): void
    {
        $snapshotFile = "offline_snapshots/modules_{$hotelId}_{$branchId}.json";
        
        $data = [
            'hotel_id' => $hotelId,
            'branch_id' => $branchId,
            'modules' => $modules,
            'updated_at' => now()->toIso8601String(),
            'version' => time(), // Module versioning
        ];

        Storage::disk('local')->put($snapshotFile, json_encode($data));
    }
}
