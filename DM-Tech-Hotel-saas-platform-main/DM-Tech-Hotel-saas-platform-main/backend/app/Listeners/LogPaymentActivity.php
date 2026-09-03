<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Services\ActivityLogService;

class LogPaymentActivity
{
    protected ActivityLogService $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function handle($event)
    {
        $transaction = $event->transaction ?? null;
        if (!$transaction) return;

        $hotelId = $transaction->hotel_id;
        $eventName = class_basename($event);

        $description = "Payment transaction {$transaction->id} - Status: {$transaction->status}";
        if ($eventName === 'PaymentRefunded') {
            $description .= " (Refund Amount: {$event->refundAmount})";
        }

        $this->activityLogService->logSystemEvent(
            $hotelId,
            $eventName,
            $description,
            'info',
            ['transaction_id' => $transaction->id, 'gateway' => $transaction->payment_gateway]
        );
    }
}
