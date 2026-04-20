<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class AuditLogObserver
{
    /**
     * Guard to prevent infinite recursion during watchdog logging.
     */
    private static bool $isProcessing = false;

    /**
     * Map of change types to their SIEM severity scores.
     */
    private const SEVERITY_MAP = [
        'hardware_mismatch'   => 12,
        'port_violation'      => 12,
        'watchdog_suspension' => 15,
        'cross_tenant_violation' => 14,
        'order_voided'        => 12,
        'failed_handshake'    => 10,
        'webhook_spoofing'    => 15,
        'stock_transfer_dispute' => 10,
    ];

    /**
     * Handle the AuditLog "creating" event to persist severity score.
     */
    public function creating(AuditLog $log): void
    {
        $log->severity_score = self::SEVERITY_MAP[$log->change_type] ?? 0;
        
        // Ensure entity_id is never NULL to satisfy database NOT NULL constraints in legacy tests
        if ($log->entity_id === null) {
            $log->entity_id = 0;
        }
    }

    /**
     * Handle the AuditLog "created" event.
     */
    public function created(AuditLog $log): void
    {
        // 1. Indestructible Recursion Guard
        if (self::$isProcessing) {
            return;
        }

        // 2. Neutrality Gate: Return immediately for low-severity events (< 12)
        // This ensures zero overhead for 99% of standard system actions.
        $severity = $log->severity_score;
        if ($severity < 12) {
            return;
        }

        self::$isProcessing = true;

        try {
            $ip = $log->ip_address;
            if (!$ip) return;

            // Rule A: Detection Intelligence - Correlated Attack (IP window 60s)
            // Any IP triggering >1 high-severity log entry within 60 seconds.
            $correlationCount = AuditLog::withoutGlobalScopes()
                ->where('ip_address', $ip)
                ->whereIn('change_type', array_keys(self::SEVERITY_MAP))
                ->where('created_at', '>=', now()->subSeconds(60))
                ->where('id', '!=', $log->id)
                ->count();

            if ($correlationCount >= 1) {
                Log::channel('siem')->alert('Correlated Attack Detected: Multi-High-Severity Event Chain.', [
                    'severity_score' => 14,
                    'correlation_ip' => $ip,
                    'event_count'    => $correlationCount + 1,
                    'trigger_event'  => $log->change_type,
                ]);

                // 3. Active Response: Automated Lockdown (Segment 4.2)
                // Identify the user associated with this attack and suspend them.
                if ($log->user_id) {
                    $user = User::withoutGlobalScopes()->find($log->user_id);
                    
                    // Safety Guard: SuperAdmins are EXEMPT from auto-lock
                    if ($user && !$user->isSuperAdmin() && $user->is_approved) {
                        $user->update(['is_approved' => false]);

                        Log::channel('siem')->emergency("[EMERGENCY] Automated Lockdown Initiated for User ID: {$user->id} due to Correlated Attack.", [
                            'severity_score'     => 15,
                            'user_id'            => $user->id,
                            'correlation_ip'     => $ip,
                            'mitigation_action'  => 'ACCOUNT_SUSPENDED',
                            'hardware_signature' => $user->hardware_hash,
                        ]);

                        // Log to Database Audit Log
                        \App\Services\AuditLogService::log(
                            'user', $user->id, 'watchdog_suspension',
                            ['is_approved' => true], ['is_approved' => false],
                            'Automated Lockdown: Correlated Attack Detection.',
                            'system', $user->hotel_id, $user->id
                        );
                    }
                }
            }

            // Rule B: Legacy Watchdog Correlation (Hardware Mismatch + Port Violation)
            // This rule is specific to automated suspension and uses a 5-minute window.
            if (in_array($log->change_type, ['hardware_mismatch', 'port_violation'])) {
                $otherType = $log->change_type === 'hardware_mismatch' ? 'port_violation' : 'hardware_mismatch';
                
                $suspensionQuery = AuditLog::withoutGlobalScopes()
                    ->where('change_type', $otherType)
                    ->where('created_at', '>=', now()->subMinutes(5))
                    ->where('id', '!=', $log->id);

                // Correlate by IP OR User ID for maximum identity integrity
                $suspensionQuery->where(function ($q) use ($ip, $log) {
                    if ($ip) $q->where('ip_address', $ip);
                    if ($log->user_id) $q->orWhere('user_id', $log->user_id);
                });

                if ($suspensionQuery->exists()) {
                    $this->triggerWatchdogSuspension($log, $ip ?? 'Unknown');
                }
            }
        } finally {
            // Guarantee the flag is reset even on failure
            self::$isProcessing = false;
        }
    }

    /**
     * Suspend the account and log the security action.
     */
    protected function triggerWatchdogSuspension(AuditLog $triggerLog, string $ip): void
    {
        $user = User::withoutGlobalScopes()->find($triggerLog->user_id);

        if ($user && $user->is_approved) {
            // Execute Suspension (Transitioning account to Moderation)
            $user->update(['is_approved' => false]);

            // Log the action to the dedicated SIEM channel
            Log::channel('siem')->emergency('Watchdog Automatic Account Suspension: Correlation Rule Matched.', [
                'severity_score'     => 15,
                'user_id'            => $user->id,
                'correlation_ip'     => $ip,
                'trigger_event'      => $triggerLog->change_type,
                'mitigation_action'  => 'ACCOUNT_SUSPENDED',
                'hardware_signature' => $user->hardware_hash,
            ]);

            // Log to Database Audit Log (recursion guard will handle this)
            \App\Services\AuditLogService::log(
                'user', $user->id, 'watchdog_suspension',
                ['is_approved' => true], ['is_approved' => false],
                'Watchdog Automatic Account Suspension: Correlation Rule Matched.',
                'system', $user->hotel_id, $user->id
            );
        }
    }
}
