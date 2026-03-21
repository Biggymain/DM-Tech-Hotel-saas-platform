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
     * @return string The public URL of the uploaded image
     */
    public function upload(UploadedFile $file, string $folder = 'uploads'): string
    {
        $disk = config('filesystems.default') === 'gcs' ? 'gcs' : 'public';
        
        // Use a unique filename to prevent overwrites and security issues
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs($folder, $filename, $disk);

        if ($disk === 'gcs') {
            return Storage::disk('gcs')->url($path);
        }

        return asset(Storage::url($path));
    }
}
