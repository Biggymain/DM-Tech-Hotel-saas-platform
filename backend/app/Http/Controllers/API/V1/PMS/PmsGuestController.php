<?php

namespace App\Http\Controllers\API\V1\PMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Guest;

class PmsGuestController extends Controller
{
    /**
     * List all guests for the hotel.
     */
    public function index(Request $request)
    {
        $guests = Guest::where('hotel_id', $request->user()->hotel_id)->get();
        return response()->json(['success' => true, 'data' => $guests]);
    }

    /**
     * Store a newly created guest.
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'identification_type' => 'nullable|string|max:50',
            'identification_number' => 'nullable|string|max:100',
        ]);

        $data = $request->all();
        $data['hotel_id'] = $request->user()->hotel_id;

        $guest = Guest::create($data);

        return response()->json([
            'success' => true,
            'data' => $guest,
            'message' => 'Guest created successfully.'
        ], 201);
    }
}
