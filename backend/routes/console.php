<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:check-expiry', function (\App\Services\SubscriptionService $service) {
    $this->info('Checking subscription expiries...');
    $service->checkExpiries();
    $this->info('Done!');
})->purpose('Check and process hotel subscription expiries');

Artisan::command('ota:sync-all', function () {
    $this->info('Syncing OTA channels for all active hotel connections...');
    $connections = \App\Models\HotelChannelConnection::where('status', 'active')->get();
    foreach ($connections as $connection) {
        \App\Jobs\SyncChannelInventoryJob::dispatch($connection->id);
        \App\Jobs\SyncChannelReservationsJob::dispatch($connection->id);
    }
    $this->info("Dispatched sync jobs for {$connections->count()} connections.");
})->purpose('Sync OTA inventory and reservations for all active channel connections')
  ->everyFiveMinutes();

Artisan::command('revenue:generate-insights', function () {
    $this->info('Generating revenue insights for all hotels...');
    \App\Jobs\GenerateRevenueInsightsJob::dispatch();
    $this->info('Revenue insights job dispatched.');
})->purpose('Generate AI-assisted revenue and pricing insights for all hotels')
  ->dailyAt('00:00');
