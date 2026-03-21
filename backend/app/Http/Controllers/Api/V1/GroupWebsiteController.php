<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelGroupWebsite;
use App\Models\HotelWebsiteOverride;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupWebsiteController extends Controller
{
    use AuthorizesRequests;
    /**
     * Get the website configuration for the current group.
     */
    public function show(Request $request)
    {
        $groupId = $this->getGroupId($request);
        $website = HotelGroupWebsite::with('group.branches.roomTypes')
            ->where(function ($query) use ($groupId) {
                $query->where('hotel_group_id', $groupId);
            })
            ->first();

        if (!$website) {
            return response()->json(['message' => 'No website configured'], 404);
        }

        return response()->json($website);
    }

    /**
     * Create or update the group website.
     */
    public function update(Request $request)
    {
        $groupId = $this->getGroupId($request);
        
        $validated = $request->validate([
            'title'           => 'required|string|max:255',
            'slug'            => 'nullable|string|unique:hotel_group_websites,slug,' . ($request->id ?? 'NULL'),
            'description'     => 'nullable|string',
            'about_text'      => 'nullable|string',
            'logo_url'        => 'nullable|string',
            'banner_url'      => 'nullable|string',
            'primary_color'   => 'nullable|string|max:10',
            'secondary_color' => 'nullable|string|max:10',
            'social_links'    => 'nullable|array',
            'features'        => 'nullable|array',
            'email'           => 'nullable|string|email|max:255',
            'phone'           => 'nullable|string|max:50',
            'address'         => 'nullable|string',
            'design_settings' => 'nullable|array',
            'is_active'       => 'boolean',
        ]);

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['title']);
        }

        $website = HotelGroupWebsite::updateOrCreate(
            ['hotel_group_id' => $groupId],
            $validated
        );

        return response()->json([
            'message' => 'Website updated successfully',
            'data'    => $website
        ]);
    }

    /**
     * Manage branch-specific overrides.
     */
    public function updateOverride(Request $request, Hotel $hotel)
    {
        // Ensure the hotel belongs to the user's group
        if ($hotel->hotel_group_id !== $request->user()->hotel_group_id) {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'custom_title'        => 'nullable|string',
            'custom_description'  => 'nullable|string',
            'custom_about_text'   => 'nullable|string',
            'primary_image_url'   => 'nullable|string',
            'secondary_image_url' => 'nullable|string',
            'use_group_branding'  => 'boolean',
            'design_settings'     => 'nullable|array',
        ]);

        $override = HotelWebsiteOverride::updateOrCreate(
            ['hotel_id' => $hotel->id],
            $validated
        );

        return response()->json([
            'message' => 'Branch override updated successfully',
            'data'    => $override
        ]);
    }

    private function getGroupId(Request $request)
    {
        // Simplification for the purpose of the task
        return $request->user()->hotel_group_id;
    }
}
