<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;

class HardwareFingerprintService
{
    /**
     * Generate a unique SHA-256 hardware hash for this machine.
     * Captures Linux product_uuid or falls back to MAC Address.
     */
    public function generateHash(): string
    {
        // Prioritizing /etc/machine-id as it's typically readable by the web user
        $identifier = $this->getMachineId();

        if (!$identifier) {
            $identifier = $this->getProductUuid();
        }

        if (!$identifier) {
            $identifier = $this->getMacAddress();
        }

        if (!$identifier) {
            throw new Exception("Unable to capture hardware fingerprint. Security lockdown imminent.");
        }

        return hash('sha256', "DM-TECH-PF-{$identifier}");
    }

    /**
     * Tries to read /sys/class/dmi/id/product_uuid.
     * Note: Requires 'sudo setfacl -m u:www-data:r /sys/class/dmi/id/product_uuid'
     */
    private function getProductUuid(): ?string
    {
        $path = '/sys/class/dmi/id/product_uuid';
        
        if (file_exists($path) && is_readable($path)) {
            $uuid = trim(file_get_contents($path));
            return !empty($uuid) ? $uuid : null;
        }

        Log::warning("Cannot read product_uuid: Permissions or file missing.");
        return null;
    }

    /**
     * Fallback 1: Machine ID
     */
    private function getMachineId(): ?string
    {
        $path = '/etc/machine-id';
        
        if (file_exists($path) && is_readable($path)) {
            return trim(file_get_contents($path));
        }

        return null;
    }

    /**
     * Fallback 2: MAC Address (eth0)
     */
    private function getMacAddress(): ?string
    {
        $path = '/sys/class/net/eth0/address';
        
        if (file_exists($path) && is_readable($path)) {
            return trim(file_get_contents($path));
        }

        // Alternative for non-eth0 interfaces
        $interface = shell_exec("ip -o link show | awk '$2 != \"lo:\" {print $2; exit}' | sed 's/://'");
        $path = "/sys/class/net/" . trim($interface) . "/address";

        if (file_exists($path) && is_readable($path)) {
            return trim(file_get_contents($path));
        }

        return null;
    }
}
