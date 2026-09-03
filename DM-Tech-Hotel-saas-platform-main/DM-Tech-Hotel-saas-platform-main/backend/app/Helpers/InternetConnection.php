<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class InternetConnection
{
    /**
     * Checks if the device has outbound internet connectivity dynamically.
     * Caches outbound evaluation locally for 10 seconds structurally avoiding API rate limitations safely.
     */
    public static function isConnected(int $timeout = 2): bool
    {
        return Cache::remember('internet_connection_status', 10, function () use ($timeout) {
            try {
                $cloudUrl = env('CLOUD_API_URL', 'https://api.omnistay.com') . '/api/ping';
                $response = Http::timeout($timeout)->get($cloudUrl);
                
                return $response->successful();
            } catch (\Exception $e) {
                return false;
            }
        });
    }
}
