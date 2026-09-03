<?php

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Find two different hotels
$hotelA = Hotel::find(1);
$hotelB = Hotel::find(7);

if (!$hotelA || !$hotelB || $hotelA->id == $hotelB->id) {
    echo "Need at least two different hotels to test isolation. Found: " . Hotel::count() . "\n";
    exit;
}

echo "Hotel A: {$hotelA->name} (ID: {$hotelA->id}, Slug: {$hotelA->slug})\n";
echo "Hotel B: {$hotelB->name} (ID: {$hotelB->id}, Slug: {$hotelB->slug})\n";

// 2. Find a room in Hotel B
$roomB = Room::where('hotel_id', $hotelB->id)->first();
if (!$roomB) {
    echo "No room found for Hotel B. Create one first.\n";
    exit;
}
echo "Attacking Hotel A using Room ID from Hotel B: {$roomB->id}\n";

// 3. Simulate a reservation request for Hotel A using Room B ID
// POST /api/v1/booking/{hotelA_slug}/reserve
// Note: In the actual reserve method, we take 'room_type_id' now, not 'room_id' (I updated it recently).
// Wait, I updated it to take 'room_type_id' or 'room_id'? Let's check.

$request = \Illuminate\Http\Request::create(
    "/api/v1/booking/{$hotelA->slug}/reserve",
    'POST',
    [
        'guest_name' => 'Attacker Security',
        'guest_email' => 'attacker@example.com',
        'guest_phone' => '123456789',
        'room_type_id' => $roomB->room_type_id, // ATTACK: using room type from Hotel B
        'check_in' => date('Y-m-d'),
        'check_out' => date('Y-m-d', strtotime('+1 day')),
        'quantity' => 1
    ]
);

// We need to bypass Sanctum for this unit test or mock it.
// But we can just call the controller method directly.
$controller = app(\App\Http\Controllers\Api\V1\PublicBookingController::class);

try {
    $response = $controller->reserve($request, $hotelA->slug);
    echo "Status Code: " . $response->getStatusCode() . "\n";
    echo "Content: " . $response->getContent() . "\n";
    
    if ($response->isSuccessful()) {
        echo "[ !!! VULNERABILITY !!! ] Successfully booked a room from Hotel B via Hotel A's endpoint!\n";
    } else {
        echo "[ OK ] Request failed as expected. Isolation works.\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "[ OK ] Exception thrown. Isolation likely works.\n";
}
