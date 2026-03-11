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
