<?php

namespace App\Http\Controllers\Api\V1\PMS;
use App\Http\Controllers\Controller;


use Illuminate\Http\Request;
use App\Models\RoomType;
use App\Services\RoomService;

class PmsRoomTypeController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    /**
     * List all room types for the hotel.
     */
    public function index(Request $request)
    {
        $roomTypes = RoomType::where('hotel_id', $request->user()->hotel_id)->get();
        return response()->json(['success' => true, 'data' => $roomTypes]);
    }

    /**
     * Store a newly created room type.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
        ]);

        $data = $request->all();
        $data['hotel_id'] = $request->user()->hotel_id;

        $roomType = $this->roomService->createRoomType($data);

        return response()->json([
            'success' => true,
            'data' => $roomType,
            'message' => 'Room Type created successfully.'
        ], 201);
    }
}
