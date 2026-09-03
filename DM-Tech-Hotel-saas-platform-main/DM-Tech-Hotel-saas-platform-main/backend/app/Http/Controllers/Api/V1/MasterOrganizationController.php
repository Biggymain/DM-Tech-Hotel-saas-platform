<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\HotelGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MasterOrganizationController extends Controller
{
    /**
     * List all organizations and their branches.
     */
    public function index()
    {
        $organizations = HotelGroup::with('branches')->get();
        return response()->json(['data' => $organizations]);
    }

    /**
     * Toggle the active status of a branch.
     */
    public function toggleStatus(Request $request, $id)
    {
        $hotel = Hotel::findOrFail($id);
        
        $hotel->is_active = !$hotel->is_active;
        $hotel->save();

        $status = $hotel->is_active ? 'ACTIVATED' : 'FROZEN';
        Log::warning("Sovereign Override: Branch {$hotel->name} has been {$status} by Master Admin " . auth()->user()->email);

        return response()->json([
            'message' => "Branch {$hotel->name} is now {$status}.",
            'is_active' => $hotel->is_active
        ]);
    }
}
