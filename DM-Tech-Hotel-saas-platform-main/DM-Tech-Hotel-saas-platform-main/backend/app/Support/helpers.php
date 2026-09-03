<?php

use Illuminate\Support\Facades\Storage;

if (!function_exists('get_secure_url')) {
    /**
     * Get a secure URL for a tenant asset.
     * If the path contains '/private/', returns a temporary signed URL.
     * Otherwise, returns a standard public URL.
     */
    function get_secure_url(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // If it's already a full URL (legacy), return it as is
        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        $disk = config('filesystems.default') === 'gcs' ? 'gcs' : 'public';

        if (str_contains($path, '/private/')) {
            if ($disk === 'gcs') {
                return Storage::disk('gcs')->temporaryUrl($path, now()->addMinutes(10));
            }
            
            // Fallback for local development or non-GCS/S3 disks
            return Storage::disk($disk)->url($path);
        }

        return Storage::disk($disk)->url($path);
    }
}
