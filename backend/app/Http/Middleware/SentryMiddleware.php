<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Hotel;
use App\Services\AuditLogService;
use App\Services\FortressLockService;
use App\Services\HardwareFingerprintService;
use App\Services\HardwareValidationService;

class SentryMiddleware
{
    protected FortressLockService $lockService;
    protected HardwareFingerprintService $fingerprintService;
    protected HardwareValidationService $validationService;

    public function __construct(
        FortressLockService $lockService, 
        HardwareFingerprintService $fingerprintService,
        HardwareValidationService $validationService
    ) {
        $this->lockService = $lockService;
        $this->fingerprintService = $fingerprintService;
        $this->validationService = $validationService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // 0. System Integrity: Kill-Switch Gate (Global Lockdown)
        if ($this->lockService->isLocked()) {
            return response()->json([
                'error' => 'SYSTEM LOCKDOWN',
                'message' => 'The Digital Fortress is sealed due to a security breach. Contact the Super Admin.'
            ], 503);
        }

        // 1. Global Hardware Sentry (Licensing & Device Control)
        $this->enforceGlobalHardwareSentry($request);

        $user = $request->user() ?? auth('sanctum')->user();
        
        // 2. Identity Moderation: Approval Bridge (Highest Priority User Gate)
        if ($user && isset($user->is_approved) && !$user->is_approved && !$user->is_super_admin) {
             return response()->json([
                'error' => 'Identity Pending Moderation',
                'message' => 'Your hardware marriage is pending Super Admin approval.'
            ], 503);
        }

        // Resolve the port and tenant context
        $currentPort = (int) ($request->header('X-Frontend-Port') ?? $request->getPort());
        $tenantId = app()->bound('tenant_id') ? app('tenant_id') : null;
        $hardwareMatch = true;

        // 3. User-Level Hardware Marriage Enforcement
        if ($user && $user->hardware_hash) {
            $currentHash = $request->header('X-Hardware-Id');
            $hardwareMatch = ($currentHash === $user->hardware_hash);

            if (!$hardwareMatch) {
                // Log Severity 12: Hardware Mismatch
                Log::channel('siem')->critical('Hardware Mismatch detected.', [
                    'severity_score' => 12,
                    'user_id' => $user->id,
                    'expected_hash' => substr($user->hardware_hash, 0, 8) . '...',
                ]);

                AuditLogService::log(
                    'user', $user->id, 'hardware_mismatch',
                    ['hash' => $user->hardware_hash], ['attempted' => $currentHash],
                    'Hardware mismatch detected', 'api', null, $user->id
                );

                // Forbidden for Hardware Mismatch
                abort(403, 'Hardware Not Registered');
            }
        }

        // SIEM Injection: Enrich all logs with security context
        Log::withContext([
            'request_ip'          => $request->ip(),
            'destination_port'    => $currentPort,
            'user_id'             => $user?->id,
            'hardware_hash_match' => $hardwareMatch,
            'support_session_active' => session('support_session_active') === true,
        ]);

        // 4. Organization Must-Match Rule (Tenancy Scoping)
        if ($user && !$user->is_super_admin && $tenantId) {
            $isAuthorized = false;

            if ($user->hotel_id == $tenantId) {
                $isAuthorized = true;
            } elseif ($user->isGroupAdmin()) {
                $targetHotel = Hotel::withoutGlobalScopes()->find($tenantId);
                if ($targetHotel && $targetHotel->hotel_group_id == $user->hotel_group_id) {
                    $isAuthorized = true;
                }
            }

            if (!$isAuthorized) {
                // Log Severity 14: Unauthorized Cross-Tenant Access Attempt
                Log::channel('siem')->alert('Unauthorized Cross-Tenant Access Attempt', [
                    'severity_score' => 14,
                    'user_id' => $user->id,
                    'target_tenant' => $tenantId,
                ]);

                AuditLogService::log(
                    'user', $user->id, 'cross_tenant_violation',
                    ['target' => $tenantId], ['ip' => $request->ip()],
                    'Unauthorized cross-tenant access attempt', 'api', null, $user->id
                );

                abort(403, 'Unauthorized Tenant Context');
            }
        }

        // 5. Digital Fortress: Strict Port Enforcement (SIEM Logic)
        if ($user && !$user->is_super_admin) {
            $portMapping = config('fortress.port_mapping', []);
            $assignedPort = null;

            foreach ($user->roles()->withoutGlobalScopes()->get() as $role) {
                if (isset($portMapping[$role->slug])) {
                    $assignedPort = $portMapping[$role->slug];
                    break;
                }
            }

            if (app()->environment('testing')) {
                \Log::info("Sentry Trace", [
                    'curr' => (int)$currentPort,
                    'ass' => (int)$assignedPort,
                    'roles' => $user->roles()->withoutGlobalScopes()->pluck('slug')->toArray()
                ]);
            }

            if ($assignedPort && (int)$currentPort !== (int)$assignedPort) {
                // Log Port Violation for SIEM (Severity 12)
                Log::channel('siem')->critical('Port Violation: Unauthorized Access Attempt', [
                    'severity_score' => 12,
                    'assigned_port'  => $assignedPort,
                ]);

                AuditLogService::log(
                    'user', $user->id, 'port_violation',
                    ['port' => $assignedPort], ['attempted' => $currentPort],
                    'Unauthorized port access attempt', 'api', null, $user->id
                );

                // Ghost the port (404 instead of 403)
                abort(404);
            }
        }

        return $next($request);
    }

    /**
     * Enforce global device licensing via HardwareValidationService.
     */
    protected function enforceGlobalHardwareSentry(Request $request): void
    {
        try {
            $hash = (app()->environment('testing') && $request->hasHeader('X-Hardware-Id'))
                ? $request->header('X-Hardware-Id')
                : $this->fingerprintService->generateHash();
        } catch (\Exception $e) {
            abort(403, 'Security integrity breach: Hardware Fingerprint capture failed.');
        }

        $device = $this->validationService->validate($hash);
        
        if (!$device && app()->environment('testing')) {
            Log::info("Hardware Validation Failed for Hash: " . ($hash ?? 'NULL'));
        }

        if (!$device) {
            abort(403, 'Hardware Not Registered');
        }

        // Handle both object (from direct DB) and array (from cache/mock) formats
        $isLocked = is_array($device) ? $device['is_manually_locked'] : $device->is_manually_locked;
        $expiresAt = is_array($device) ? $device['expires_at'] : $device->expires_at;
        $isActive = is_array($device) ? $device['device_active'] : $device->device_active;

        if ($isLocked) {
            abort(403, 'Branch manually locked');
        }

        if (now()->gt($expiresAt)) {
            abort(403, 'License Expired');
        }

        if (!$isActive) {
            abort(403, 'Device Deactivated');
        }
    }
}
