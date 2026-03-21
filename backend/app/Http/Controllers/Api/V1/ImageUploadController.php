<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ImageUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ImageUploadController extends Controller
{
    protected $uploadService;

    public function __construct(ImageUploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload an image and return its URL.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image'  => 'required|image|mimes:jpeg,png,jpg,gif,svg,webp|max:5120',
            'folder' => 'nullable|string|max:50',
        ]);

        try {
            $folder = $request->input('folder', 'website');
            $url = $this->uploadService->upload($request->file('image'), $folder);

            return response()->json([
                'message' => 'Image uploaded successfully',
                'url'     => $url,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Image upload failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to upload image',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }
}
