<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\HotelGroup;
use App\Models\Hotel;

class ThemeController extends Controller
{
    /**
     * Get theme data based on X-Group-ID header.
     * Used on Port 3005 (Booking) to fetch and apply branding.
     */
    public function getTheme(Request $request)
    {
        $groupId = $request->header('X-Group-ID');
        $hotelId = $request->header('X-Hotel-Context');

        $themeData = [
            'mode' => 'dark',
            'colors' => [
                'primary' => '#4f46e5', // Indigo-600
                'background' => '#0f172a', // Slate-900
            ],
            'logo_url' => '/logos/default.png',
            'template' => 'modern',
        ];

        if ($groupId) {
            $group = HotelGroup::find($groupId);
            if ($group) {
                // In a real scenario, this would be fetched from a 'branding_settings' field or table
                $themeData['logo_url'] = $group->logo_url ?? $themeData['logo_url'];
                $themeData['template'] = $group->preferred_template ?? 'luxury';
                
                // Example mock colors based on template
                if ($themeData['template'] === 'luxury') {
                    $themeData['colors']['primary'] = '#d4af37'; // Gold
                    $themeData['colors']['background'] = '#1a1a1a'; // Deep Black
                } elseif ($themeData['template'] === 'budget') {
                    $themeData['colors']['primary'] = '#10b981'; // Emerald
                    $themeData['colors']['background'] = '#f8fafc'; // Light
                    $themeData['mode'] = 'light';
                }
            }
        }

        return response()->json($themeData);
    }
}
