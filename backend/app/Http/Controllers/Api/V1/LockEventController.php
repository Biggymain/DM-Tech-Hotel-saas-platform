<?php

namespace App\Http\Controllers\Api\V1;
use App\Http\Controllers\Controller;




use App\Models\DigitalKey;
use App\Models\Hotel;
use App\Models\HotelSetting;
use App\Models\LockEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * LockEventController
 *
 * Handles incoming webhooks from door lock providers (VingCard, SALTO, etc.).
 * These webhooks arrive WITHOUT a user session, so we must validate every
 * request using a Shared Secret (HMAC-SHA256 signature) to prevent spoofing.
 *
 * ── SECURITY ──────────────────────────────────────────────────────────────────
 * Each provider sends one of:
 *  a) X-Signature header: HMAC-SHA256(raw body, hotel's webhook_shared_secret)
 *  b) X-Api-Key header: static shared secret set in HotelSettings
 *
 * Both are stored encrypted in HotelSettings under key 'lock_webhook_secret'.
 * A timing-safe comparison is used to prevent timing attacks.
 * ─────────────────────────────────────────────────────────────────────────────
 */
class LockEventController extends Controller
{
    /**
     * POST /api/v1/integration/lock-events
     *
     * Accepts lock hardware events: door_open, door_close, invalid_key, low_battery.
     * hotel_slug identifies which hotel this event belongs to.
     */
    public function receive(Request $request)
    {
        // ── 1. Identify the hotel from the URL slug or custom header ──────────
        $hotelSlug = $request->input('hotel_slug') ?? $request->header('X-Hotel-Slug');
        $hotel = Hotel::withoutGlobalScopes()
            ->where('subdomain_slug', $hotelSlug)
            ->orWhere('domain', $hotelSlug)
            ->first();

        if (!$hotel) {
            Log::warning("[LockWebhook] Unknown hotel slug: {$hotelSlug}");
            return response()->json(['error' => 'Hotel not found'], 404);
        }

        // ── 2. Validate the webhook signature ─────────────────────────────────
        if (!$this->verifySignature($request, $hotel)) {
            Log::warning("[LockWebhook] Invalid signature from {$request->ip()} for hotel #{$hotel->id}");
            // Return 401 — do NOT reveal details to prevent fingerprinting
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // ── 3. Parse and validate the payload ────────────────────────────────
        $validated = $request->validate([
            'event_type'  => 'required|string|in:door_open,door_close,invalid_key,low_battery,door_forced,tamper',
            'room_number' => 'required|string',
            'trigger_by'  => 'nullable|string',
            'occurred_at' => 'nullable|date',
            'provider'    => 'nullable|string',
        ]);

        // ── 4. Log the event for audit trail ─────────────────────────────────
        $event = LockEvent::create([
            'hotel_id'       => $hotel->id,
            'room_number'    => $validated['room_number'],
            'event_type'     => $validated['event_type'],
            'trigger_by'     => $validated['trigger_by'] ?? null,
            'provider'       => $validated['provider'] ?? 'unknown',
            'raw_payload'    => $request->all(),
            'webhook_source' => $request->ip(),
            'occurred_at'    => $validated['occurred_at'] ?? now(),
        ]);

        // ── 5. Handle security-critical events ───────────────────────────────
        $this->handleCriticalEvent($hotel, $event);

        Log::info("[LockWebhook] Event #{$event->id} ({$event->event_type}) logged for hotel #{$hotel->id} room {$event->room_number}");

        // Always return 200 quickly — never reveal internal state
        return response()->json(['received' => true]);
    }

    /**
     * GET /api/v1/integration/lock-events
     * Returns recent lock events for the admin dashboard (auth-protected).
     */
    public function index(Request $request)
    {
        $events = LockEvent::where('hotel_id', $request->user()->hotel_id)
            ->orderByDesc('occurred_at')
            ->limit(100)
            ->get();

        return response()->json(['data' => $events]);
    }

    // ─── Signature Validation ─────────────────────────────────────────────────

    private function verifySignature(Request $request, Hotel $hotel): bool
    {
        $sharedSecret = HotelSetting::where('hotel_id', $hotel->id)
            ->where('key', 'lock_webhook_secret')
            ->value('value');

        if (!$sharedSecret) {
            // If no secret is configured, reject by default for safety
            Log::warning("[LockWebhook] No webhook secret configured for hotel #{$hotel->id} — rejecting.");
            return false;
        }

        // Strategy A: HMAC-SHA256 signature in header (VingCard, SALTO style)
        $signature = $request->header('X-Signature') ?? $request->header('X-Hub-Signature-256');
        if ($signature) {
            $rawBody  = $request->getContent();
            $expected = 'sha256=' . hash_hmac('sha256', $rawBody, $sharedSecret);
            // Timing-safe comparison to prevent timing attacks
            return hash_equals($expected, $signature);
        }

        // Strategy B: Static API key in header (simpler setups)
        $apiKey = $request->header('X-Api-Key');
        if ($apiKey) {
            return hash_equals($sharedSecret, $apiKey);
        }

        return false;
    }

    // ─── Critical Event Handler ───────────────────────────────────────────────

    private function handleCriticalEvent(Hotel $hotel, LockEvent $event): void
    {
        // Flag high-risk events for review
        $criticalTypes = ['invalid_key', 'door_forced', 'tamper'];

        if (in_array($event->event_type, $criticalTypes)) {
            Log::critical(
                "[LockWebhook] ⚠️  SECURITY EVENT: {$event->event_type} on room {$event->room_number} " .
                "at hotel #{$hotel->id}. Source: {$event->webhook_source}"
            );
            // TODO: Dispatch AlertSecurityTeam notification in production
        }
    }
}
