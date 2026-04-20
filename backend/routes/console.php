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

Artisan::command('pms:night-audit', function () {
    $this->info('Starting automated night audit...');
    $this->call('pms:night-audit');
    $this->info('Night audit finished.');
})->purpose('Post daily room charges for all active reservations')
  ->dailyAt('02:00');

\Illuminate\Support\Facades\Schedule::command('sync:cleanup')->daily();

\Illuminate\Support\Facades\Schedule::job(new \App\Jobs\BatchSyncToCloudJob)->everyMinute()->withoutOverlapping();

\Illuminate\Support\Facades\Schedule::command('reports:monthly-stakeholder')->monthlyOn(1, '01:00');

\Illuminate\Support\Facades\Schedule::call(function () {
    app(\App\Services\SessionSentryService::class)->reapPendingSessions();
})->everyFiveMinutes()->name('guest-session-reaper');
