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

    protected function getHotelId(Request $request)
    {
        return app()->bound('tenant_id') ? app('tenant_id') : $request->user()->hotel_id;
    }

    /**
     * List all room types for the hotel.
     */
    public function index(Request $request)
    {
        $hotelId = $this->getHotelId($request);
        if (!$hotelId && !$request->user()->is_super_admin) {
            return response()->json(['success' => false, 'message' => 'No hotel context'], 400);
        }

        $query = RoomType::query();
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }

        return response()->json(['success' => true, 'data' => $query->get()]);
    }

    /**
     * Store a newly created room type.
     */
    public function store(Request $request)
    {
        $hotelId = $this->getHotelId($request);
        if (!$hotelId) {
            return response()->json(['success' => false, 'message' => 'No hotel context for room type creation'], 400);
        }

        $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
        ]);

        $data = $request->all();
        $data['hotel_id'] = $hotelId;

        $roomType = $this->roomService->createRoomType($data);

        return response()->json([
            'success' => true,
            'data' => $roomType,
            'message' => 'Room Type created successfully.'
        ], 201);
    }

    /**
     * Display the specified room type.
     */
    public function show(Request $request, $id)
    {
        $hotelId = $this->getHotelId($request);
        $query = RoomType::query();
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }
        
        $roomType = $query->findOrFail($id);
        return response()->json(['success' => true, 'data' => $roomType]);
    }

    /**
     * Update the specified room type.
     */
    public function update(Request $request, $id)
    {
        $hotelId = $this->getHotelId($request);
        $query = RoomType::query();
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }
        
        $roomType = $query->findOrFail($id);

        $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string',
            'base_price' => 'sometimes|required|numeric|min:0',
            'capacity' => 'sometimes|required|integer|min:1',
        ]);

        $roomType->update($request->all());

        return response()->json([
            'success' => true,
            'data' => $roomType,
            'message' => 'Room Type updated successfully.'
        ]);
    }

    /**
     * Remove the specified room type.
     */
    public function destroy(Request $request, $id)
    {
        $hotelId = $this->getHotelId($request);
        $query = RoomType::query();
        if ($hotelId) {
            $query->where('hotel_id', $hotelId);
        }
        
        $roomType = $query->findOrFail($id);

        if ($roomType->rooms()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete room type because it has associated rooms.'
            ], 400);
        }

        $roomType->delete();

        return response()->json([
            'success' => true,
            'message' => 'Room Type deleted successfully.'
        ]);
    }
}

