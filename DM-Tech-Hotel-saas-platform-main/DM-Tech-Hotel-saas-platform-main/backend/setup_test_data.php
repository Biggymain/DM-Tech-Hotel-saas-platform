<?php

use App\Models\Hotel;
use App\Models\Room;
use App\Models\RoomType;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$hotelId = 1; // Curl Test Branch
$hotel = Hotel::find($hotelId);

if (!$hotel) {
    echo "Hotel 1 not found.\n";
    exit;
}

$rt = RoomType::updateOrCreate(
    ['hotel_id' => $hotelId, 'name' => 'Security Test Room Type'],
    ['base_price' => 100, 'capacity' => 2, 'description' => 'Test', 'is_public' => true]
);

Room::updateOrCreate(
    ['hotel_id' => $hotelId, 'room_number' => 'SEC-101'],
    ['room_type_id' => $rt->id, 'status' => 'available']
);

echo "Test data for Hotel 1 created successfully.\n";
