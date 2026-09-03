<?php

use App\Models\Hotel;
use App\Models\HotelGroup;
use App\Models\HotelGroupWebsite;
use App\Models\HotelWebsiteOverride;
use Illuminate\Support\Str;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Target Hotel Group 6 (Fayoyiwa Michael's group)
$group = HotelGroup::find(6);

if (!$group) {
    echo "No hotel group found.\n";
    exit(1);
}

echo "Seeding website for group: {$group->name}\n";

$website = HotelGroupWebsite::updateOrCreate(
    ['hotel_group_id' => $group->id],
    [
        'title' => $group->name . ' - Official Site',
        'slug' => Str::slug($group->name),
        'description' => 'Welcome to the ultimate luxury experience. Discover our collection of premier hotels designed for your comfort.',
        'about_text' => 'We are dedicated to providing world-class hospitality services with a touch of local elegance.',
        'primary_color' => '#6366f1',
        'secondary_color' => '#a855f7',
        'is_active' => true,
        'banner_url' => 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=2070&auto=format&fit=crop',
        'logo_url' => 'https://cdn-icons-png.flaticon.com/512/2983/2983803.png'
    ]
);

foreach ($group->branches as $branch) {
    echo "Creating override for branch: {$branch->name}\n";
    HotelWebsiteOverride::updateOrCreate(
        ['hotel_id' => $branch->id],
        [
            'custom_title' => $branch->name . ' Luxury Stay',
            'custom_description' => 'A serene escape in ' . $branch->address,
            'primary_image_url' => 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=2070&auto=format&fit=crop',
            'use_group_branding' => true
        ]
    );
}

echo "Seeding complete! Group URL: /group/{$website->slug}\n";
