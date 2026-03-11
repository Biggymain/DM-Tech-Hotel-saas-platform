<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use App\Models\ChannelSyncLog;
use App\Models\HotelChannelConnection;
use App\Models\OtaChannel;
use App\Models\OtaReservation;
use App\Models\RatePlanChannelMap;
use App\Models\RoomTypeChannelMap;
use App\Services\ChannelManager\ChannelSyncService;
use App\Services\ChannelManager\ChannelWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OtaChannelController extends Controller
{
    public function __construct(
        private ChannelSyncService $syncService,
        private ChannelWebhookService $webhookService,
    ) {}

    /** List all available OTA channels */
    public function index(): JsonResponse
    {
        return response()->json(OtaChannel::where('is_active', true)->get());
    }

    /** List hotel's current channel connections with sync status */
    public function connections(Request $request): JsonResponse
    {
        $hotelId = $request->user()->hotel_id;
        return response()->json($this->syncService->getSyncStatus($hotelId));
    }

    /** Connect a hotel to an OTA channel */
    public function connect(Request $request): JsonResponse
    {
        $request->validate([
            'ota_channel_id' => 'required|exists:ota_channels,id',
            'api_key' => 'required|string',
            'api_secret' => 'nullable|string',
        ]);

        $hotelId = $request->user()->hotel_id;

        $connection = HotelChannelConnection::updateOrCreate(
            ['hotel_id' => $hotelId, 'ota_channel_id' => $request->ota_channel_id],
            [
                'api_key' => $request->api_key,
                'api_secret' => $request->api_secret,
                'refresh_token' => $request->refresh_token,
                'status' => 'active',
            ]
        );

        return response()->json(['message' => 'Channel connected successfully.', 'connection' => $connection], 201);
    }

    /** Disconnect a channel */
    public function disconnect(Request $request, int $channelId): JsonResponse
    {
        $hotelId = $request->user()->hotel_id;
        HotelChannelConnection::where('hotel_id', $hotelId)
            ->where('ota_channel_id', $channelId)
            ->update(['status' => 'disconnected']);

        return response()->json(['message' => 'Channel disconnected.']);
    }

    /** Manually trigger a sync for all hotel channels */
    public function triggerSync(Request $request): JsonResponse
    {
        $hotelId = $request->user()->hotel_id;
        $this->syncService->syncHotel($hotelId);
        return response()->json(['message' => 'Sync jobs dispatched for all active channels.']);
    }

    /** Map a room type to an OTA room identifier */
    public function mapRoomType(Request $request): JsonResponse
    {
        $request->validate([
            'ota_channel_id' => 'required|exists:ota_channels,id',
            'room_type_id' => 'required|exists:room_types,id',
            'external_room_type_id' => 'required|string',
        ]);

        $map = RoomTypeChannelMap::updateOrCreate(
            ['room_type_id' => $request->room_type_id, 'ota_channel_id' => $request->ota_channel_id],
            ['hotel_id' => $request->user()->hotel_id, 'external_room_type_id' => $request->external_room_type_id]
        );

        return response()->json(['message' => 'Room type mapped.', 'mapping' => $map], 201);
    }

    /** Map a rate plan to an OTA rate identifier */
    public function mapRatePlan(Request $request): JsonResponse
    {
        $request->validate([
            'ota_channel_id' => 'required|exists:ota_channels,id',
            'rate_plan_id' => 'required|exists:rate_plans,id',
            'external_rate_plan_id' => 'required|string',
        ]);

        $map = RatePlanChannelMap::updateOrCreate(
            ['rate_plan_id' => $request->rate_plan_id, 'ota_channel_id' => $request->ota_channel_id],
            ['hotel_id' => $request->user()->hotel_id, 'external_rate_plan_id' => $request->external_rate_plan_id]
        );

        return response()->json(['message' => 'Rate plan mapped.', 'mapping' => $map], 201);
    }

    /** View sync logs for a hotel */
    public function syncLogs(Request $request): JsonResponse
    {
        $hotelId = $request->user()->hotel_id;
        $logs = ChannelSyncLog::where('hotel_id', $hotelId)
            ->with('otaChannel')
            ->latest()
            ->limit(100)
            ->get();
        return response()->json($logs);
    }

    /** View OTA-imported reservations */
    public function otaReservations(Request $request): JsonResponse
    {
        $hotelId = $request->user()->hotel_id;
        $reservations = OtaReservation::where('hotel_id', $hotelId)
            ->with('otaChannel', 'internalReservation')
            ->latest()
            ->paginate(20);
        return response()->json($reservations);
    }

    /** Webhook receiver — public endpoint for OTA pushes */
    public function webhook(Request $request, string $channel): JsonResponse
    {
        $result = $this->webhookService->handle($channel, $request);
        return response()->json($result);
    }
}
