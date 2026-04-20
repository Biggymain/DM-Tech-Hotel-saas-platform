<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadService
{
    /**
     * Upload an image to Google Cloud Storage.
     *
     * @param UploadedFile $file
     * @param string $folder
     * @param string $visibility 'public' or 'private'
     * @return string The relative path of the uploaded image
     */
    public function upload(UploadedFile $file, string $folder = 'uploads', string $visibility = 'public'): string
    {
        $disk = config('filesystems.default') === 'gcs' ? 'gcs' : 'public';
        
        $tenantId = app()->bound('tenant_id') ? app('tenant_id') : (auth()->check() ? auth()->user()->hotel_id : 'default');
        
        // Use a unique filename to prevent overwrites and security issues
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        
        // Structure: tenants/{tenant_id}/{visibility}/{module}/{filename}
        $fullPath = "tenants/{$tenantId}/{$visibility}/{$folder}";
        
        $path = $file->storeAs($fullPath, $filename, $disk);

        return $path;
    }
}
