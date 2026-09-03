<?php

use App\Models\AuditLog;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// 1. Verify Migration Status
echo "Checking AuditLog Schema...\n";
$columns = DB::select("PRAGMA table_info(audit_logs)");
foreach ($columns as $column) {
    echo "Column: {$column->name}, Type: {$column->type}, NotNull: {$column->notnull}, Default: {$column->dflt_value}\n";
}

// 2. Attempt Reproduce
echo "\nAttempting SubscriptionPlan::updateOrCreate...\n";
try {
    SubscriptionPlan::updateOrCreate(
        ['slug' => 'test-tier'],
        [
            'name' => 'Test Tier',
            'price' => 10.00,
            'billing_cycle' => 'monthly',
            'is_active' => true
        ]
    );
    echo "Success!\n";
} catch (\Exception $e) {
    echo "Caught Error: " . $e->getMessage() . "\n";
    echo "Stack Trace: " . $e->getTraceAsString() . "\n";
}
