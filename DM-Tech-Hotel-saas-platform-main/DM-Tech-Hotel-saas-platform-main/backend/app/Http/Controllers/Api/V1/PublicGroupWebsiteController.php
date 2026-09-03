<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelGroupWebsite;
use Illuminate\Http\Request;

class PublicGroupWebsiteController extends Controller
{
    /**
     * Resolve the group website by slug and return branding + branch list.
     */
    public function show($slug)
    {
        $website = HotelGroupWebsite::where('slug', $slug)
            ->where('is_active', true)
            ->with(['group.branches' => function ($query) {
                $query->where('is_active', true)
                    ->with('websiteOverride');
            }])
            ->firstOrFail();

        return response()->json([
            'group_website' => $website,
            'branches'      => $website->group->branches->map(function ($branch) use ($website) {
                $override = $branch->websiteOverride;
                return [
                    'id'              => $branch->id,
                    'name'            => $branch->name,
                    'slug'            => $branch->slug,
                    'description'     => $override->custom_description ?? $website->description,
                    'image_url'       => $override->primary_image_url   ?? $website->banner_url,
                    'address'         => $branch->address,
                    'design_settings' => ($override && $override->use_group_branding === false) 
                                         ? $override->design_settings 
                                         : $website->design_settings,
                ];
            })
        ]);
    }

    /**
     * Get unique room types and pricing for a specific branch.
     */
    public function branchDetails($group_slug, $hotel_slug)
    {
        $hotel = Hotel::where('slug', $hotel_slug)
            ->whereHas('group.website', function ($query) use ($group_slug) {
                $query->where('slug', $group_slug);
            })
            ->with(['roomTypes', 'websiteOverride'])
            ->firstOrFail();

        return response()->json([
            'hotel'      => $hotel,
            'room_types' => $hotel->roomTypes,
        ]);
    }

    /**
     * GET /api/v1/public/group-website
     * Returns slug of the first active group website.
     * Used by the SaaS root page to redirect guests to the correct portal.
     */
    public function findFirst()
    {
        $website = HotelGroupWebsite::where('is_active', true)
            ->orderBy('id')
            ->first(['slug', 'title']);

        if (!$website) {
            return response()->json(['error' => 'No active group website found'], 404);
        }

        return response()->json(['slug' => $website->slug, 'title' => $website->title]);
    }
}
