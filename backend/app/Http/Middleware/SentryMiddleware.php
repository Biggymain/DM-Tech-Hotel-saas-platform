<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Services\HardwareFingerprintService;

class SentryMiddleware
{
    protected $fingerprintService;
    protected $lockService;

    public function __construct(HardwareFingerprintService $fingerprintService, \App\Services\FortressLockService $lockService)
    {
        $this->fingerprintService = $fingerprintService;
        $this->lockService = $lockService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Digital Fortress: Hard Lockdown Check
        if ($this->lockService->isLocked()) {
            return response()->json([
                'status' => 'error',
                'message' => 'SYSTEM LOCKDOWN: SECURITY INTEGRITY BREACH. Recovery required by System Administrator.',
                'code' => 'FORTRESS_LOCKDOWN'
            ], 503);
        }

        // Production Mode: Verify Hardware Fingerprint & Branch Lock
        $hardwareHash = $this->fingerprintService->generateHash();

        $cacheKey = "licensing_sentry_{$hardwareHash}";

        $licenseStatus = Cache::remember($cacheKey, 3600, function() use ($hardwareHash) {
            $device = DB::connection('supabase')->table('devices')
                ->join('branches', 'devices.branch_id', '=', 'branches.id')
                ->where('devices.hardware_hash', $hardwareHash)
                ->where('devices.is_active', true)
                ->select(
                    'branches.is_manually_locked',
                    'branches.expires_at',
                    'branches.manager_email',
                    'branches.owner_email',
                    'devices.is_active as device_active'
                )
                ->first();

            if (!$device) {
                return ['status' => 'UNREGISTERED'];
            }

            if ($device->is_manually_locked) {
                return ['status' => 'LOCKED'];
            }

            if (now()->isAfter($device->expires_at)) {
                return ['status' => 'EXPIRED'];
            }

            return [
                'status' => 'ACTIVE',
                'expires_at' => $device->expires_at,
                'manager_email' => $device->manager_email,
                'owner_email' => $device->owner_email,
            ];
        });

        if ($licenseStatus['status'] !== 'ACTIVE') {
            $message = match($licenseStatus['status']) {
                'UNREGISTERED' => 'Hardware Not Registered. This device is not authorized for this branch.',
                'LOCKED' => 'Subscription Suspended: Branch manually locked by platform owner.',
                'EXPIRED' => 'Subscription Suspended: Your branch license has expired.',
                default => 'Subscription Suspended: General licensing error.'
            };
            
            $errorResponse = [
                'status' => 'error',
                'message' => $message,
                'code' => "LICENSE_{$licenseStatus['status']}"
            ];

            if (isset($licenseStatus['manager_email'])) {
                $errorResponse['manager_email'] = $licenseStatus['manager_email'];
                $errorResponse['owner_email'] = $licenseStatus['owner_email'];
            }

            return response()->json($errorResponse, 403);
        }

        return $next($request);
    }
}
