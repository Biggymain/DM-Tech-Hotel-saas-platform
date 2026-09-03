<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Checkout Alert Threshold
    |--------------------------------------------------------------------------
    |
    | This value defines the number of hours before checkout specifically 
    | when a notification/badge should appear on the Staff KDS (Port 3003).
    |
    */
    'checkout_alert_threshold' => env('CHECKOUT_ALERT_THRESHOLD', 2),
];
