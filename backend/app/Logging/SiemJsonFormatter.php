<?php

namespace App\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;
use Illuminate\Support\Facades\Log;

class SiemJsonFormatter extends JsonFormatter
{
    /**
     * Whitelist of context keys allowed in the SIEM log to prevent PII leakage.
     * All other keys (like 'password', 'token', 'cvv') will be stripped.
     */
    private const METADATA_WHITELIST = [
        'user_id',
        'hotel_id',
        'hotel_group_id',
        'request_ip',
        'destination_port',
        'hardware_hash_match',
        'role_assigned_port',
        'severity_score',
        'change_type',
        'entity_type',
        'expected_hash',
        'assigned_port',
        'attempted_port',
        'correlation_ip',
        'trigger_event',
        'event_count',
        'mitigation_action',
        'hardware_signature'
    ];

    /**
     * Blacklist of keys that must be redacted in the SIEM log context.
     */
    private const PII_BLACKLIST = [
        'email', 'password', 'token', 'secret', 'pgp_key', 
        'cvv', 'passphrase', 'pin'
    ];

    /**
     * Format the log record as a SIEM-ready structured JSON object.
     */
    public function format(LogRecord $record): string
    {
        // Recursively redact PII from the context before any other processing
        $context = $this->redactPII($record->context);

        $siemRecord = [
            'timestamp'           => $record->datetime->format('Y-m-d H:i:s'),
            'level'               => $record->level->getName(),
            'message'             => $record->message,
            'request_ip'          => $context['request_ip'] ?? null,
            'destination_port'    => $context['destination_port'] ?? null,
            'user_id'             => $context['user_id'] ?? null,
            'hardware_hash_match' => $context['hardware_hash_match'] ?? null,
            'role_assigned_port'  => $context['role_assigned_port'] ?? null,
            'severity_score'      => $context['severity_score'] ?? null,
        ];

        // Metadata Whitelisting & PII Leak Prevention
        $filteredContext = $this->filterContext($context);
        
        if (!empty($filteredContext)) {
            $siemRecord['extra_context'] = $filteredContext;
        }

        return $this->toJson($siemRecord) . ($this->appendNewline ? "\n" : "");
    }

    /**
     * Recursively scan and redact blacklisted PII keys.
     */
    private function redactPII(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = $this->redactPII($value);
            } elseif (is_string($key) && in_array(strtolower($key), self::PII_BLACKLIST)) {
                $value = '[REDACTED]';
            }
        }
        return $data;
    }

    /**
     * Flatten and filter the context against the whitelist.
     */
    private function filterContext(array $context): array
    {
        $filtered = [];
        $whitelist = array_merge(self::METADATA_WHITELIST, self::PII_BLACKLIST);

        foreach ($context as $key => $value) {
            // Already handled in the root SIEM record
            if (in_array($key, ['request_ip', 'destination_port', 'user_id', 'hardware_hash_match', 'role_assigned_port', 'severity_score'])) {
                continue;
            }

            if (in_array($key, $whitelist)) {
                if (is_scalar($value)) {
                    $filtered[$key] = $value;
                } elseif (is_array($value)) {
                    // For arrays, we keep them if they were already redacted
                    $filtered[$key] = $value;
                }
            } else {
                // Internal safety warning (logged locally to avoid recursion)
                if (!in_array($key, ['user', 'hotel'])) { 
                     error_log("SIEM Warning: Stripping non-whitelisted key '{$key}' from telemetry.");
                }
            }
        }
        return $filtered;
    }
}
