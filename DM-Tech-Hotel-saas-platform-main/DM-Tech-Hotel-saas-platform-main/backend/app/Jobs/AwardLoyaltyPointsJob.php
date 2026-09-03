<?php

namespace App\Jobs;

use App\Models\Guest;
use App\Models\HotelSetting;
use App\Models\Payment;
use App\Models\LoyaltyTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AwardLoyaltyPointsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $paymentId) {}

    public function handle(): void
    {
        $payment = Payment::with(['invoice.order.guest'])->find($this->paymentId);
        
        if (!$payment || !$payment->invoice || !$payment->invoice->order || !$payment->invoice->order->guest) {
            return;
        }

        $guest = $payment->invoice->order->guest;

        if (!$guest->is_onboarded) {
            return;
        }

        $conversionRate = HotelSetting::where('hotel_id', $payment->hotel_id)
            ->where('setting_key', 'loyalty_conversion_rate')
            ->first();

        $rateValue = $conversionRate ? (float) $conversionRate->setting_value : 0;

        if ($rateValue <= 0) {
            return;
        }

        $points = (int) floor($payment->amount / $rateValue);

        if ($points <= 0) {
            return;
        }

        DB::transaction(function () use ($guest, $points, $payment) {
            $guest->increment('loyalty_points', $points);

            LoyaltyTransaction::create([
                'hotel_id' => $payment->hotel_id,
                'guest_id' => $guest->id,
                'outlet_id' => $payment->invoice->outlet_id ?? null,
                'type' => 'earn',
                'points' => $points,
                'reference_type' => get_class($payment),
                'reference_id' => $payment->id,
                'reason' => "Points earned from payment #{$payment->id}",
            ]);
        });

        Log::info("Awarded {$points} points to Guest #{$guest->id} for Payment #{$payment->id}");
    }
}
