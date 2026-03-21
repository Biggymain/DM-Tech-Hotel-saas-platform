<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Checking Database Connection...\n";
try {
    DB::connection()->getPdo();
    echo "DB Connected!\n";
} catch (\Exception $e) {
    echo "DB Connection Failed: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Checking 'users' table columns...\n";
$columns = Schema::getColumnListing('users');
echo "Columns: " . implode(', ', $columns) . "\n";

if (!in_array('hotel_group_id', $columns)) {
    echo "ERROR: 'hotel_group_id' MISSING from 'users' table!\n";
} else {
    echo "OK: 'hotel_group_id' present.\n";
}

echo "Checking Redis Connection...\n";
try {
    Redis::ping();
    echo "Redis OK!\n";
} catch (\Exception $e) {
    echo "Redis Failed: " . $e->getMessage() . "\n";
}

echo "Checking Roles Table...\n";
if (Schema::hasTable('roles')) {
    $rolesCount = DB::table('roles')->count();
    echo "Roles count: $rolesCount\n";
    $roles = DB::table('roles')->pluck('slug')->toArray();
    echo "Roles: " . implode(', ', $roles) . "\n";
    $roleColumns = Schema::getColumnListing('roles');
    echo "Role Columns: " . implode(', ', $roleColumns) . "\n";
} else {
    echo "ERROR: 'roles' table missing!\n";
}
