<?php

namespace App\Http\Controllers\API\V1\PMS;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Room;
use App\Services\RoomService;

class PmsRoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    /**
     * List all rooms for the hotel.
     */
    public function index(Request $request)
    {
        $rooms = Room::with('roomType')->where('hotel_id', $request->user()->hotel_id)->get();
        return response()->json(['success' => true, 'data' => $rooms]);
    }

    /**
     * Store a newly created room.
     */
    public function store(Request $request)
    {
        $request->validate([
            'room_type_id' => 'required|exists:room_types,id',
            'room_number' => 'required|string|max:50',
            'floor' => 'nullable|string|max:50',
        ]);

        $data = $request->all();
        $data['hotel_id'] = $request->user()->hotel_id;

        $room = $this->roomService->createRoom($data);

        return response()->json([
            'success' => true,
            'data' => $room->load('roomType'),
            'message' => 'Room created successfully.'
        ], 201);
    }

    /**
     * Update room status.
     */
    public function updateStatus(Request $request, Room $room)
    {
        if ($room->hotel_id !== $request->user()->hotel_id) {
            abort(403, 'Unauthorized access to this room.');
        }

        $request->validate([
            'status' => 'required|in:available,occupied,maintenance,out_of_order',
            'maintenance_notes' => 'nullable|string',
            'maintenance_until' => 'nullable|date',
        ]);

        $this->roomService->updateRoomStatus(
            $room,
            $request->status,
            $request->maintenance_notes,
            $request->maintenance_until
        );

        return response()->json([
            'success' => true,
            'data' => $room->fresh(),
            'message' => 'Room status updated successfully.'
        ]);
    }

    /**
     * Update housekeeping status.
     */
    public function updateHousekeeping(Request $request, Room $room)
    {
        if ($room->hotel_id !== $request->user()->hotel_id) {
            abort(403, 'Unauthorized access to this room.');
        }

        $request->validate([
            'housekeeping_status' => 'required|in:clean,dirty,cleaning,inspecting',
        ]);

        $this->roomService->updateHousekeepingStatus($room, $request->housekeeping_status);

        return response()->json([
            'success' => true,
            'data' => $room->fresh(),
            'message' => 'Housekeeping status updated successfully.'
        ]);
    }

    /**
     * Get rooms grouped by floor for visual map.
     */
    public function roomMap(Request $request)
    {
        $rooms = Room::with('roomType')
            ->where('hotel_id', $request->user()->hotel_id)
            ->orderBy('floor', 'asc')
            ->orderBy('room_number', 'asc')
            ->get();

        $grouped = $rooms->groupBy(function($room) {
            return $room->floor ?? 'Ground Floor';
        });

        return response()->json([
            'success' => true,
            'data' => $grouped
        ]);
    }
}
