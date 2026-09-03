<?php

namespace App\Jobs;

use App\Models\Hotel;
use App\Models\RevenueConfig;
use App\Models\RoomType;
use App\Services\RevenueIntelligenceService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateRevenueInsightsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(RevenueIntelligenceService $service): void
    {
        $hotels = Hotel::all();
        $startDate = Carbon::today();
        $endDate = Carbon::today()->addDays(30); // Project for the next 30 days

        foreach ($hotels as $hotel) {
            try {
                $insights = $service->generateInsights($hotel, $startDate, $endDate);
                Log::info("Revenue insights generated for hotel ID: {$hotel->id}");

                // Phase 3: Auto-apply pricing if enabled
                $config = RevenueConfig::where('hotel_id', $hotel->id)->first();
                if ($config && $config->auto_apply_enabled) {
                    $this->applyPricingRules($hotel, $insights->first());
                }
            } catch (\Exception $e) {
                Log::error("Failed to generate revenue insights for hotel ID: {$hotel->id}. Error: {$e->getMessage()}");
            }
        }
    }

    /**
     * Apply suggested pricing to room types.
     */
    protected function applyPricingRules(Hotel $hotel, $todayInsight): void
    {
        if (!$todayInsight || empty($todayInsight->recommended_rate_adjustment)) {
            return;
        }

        foreach ($todayInsight->recommended_rate_adjustment as $recommendation) {
            $roomType = RoomType::find($recommendation['room_type_id']);
            if ($roomType && $roomType->hotel_id === $hotel->id) {
                $roomType->update([
                    'base_price' => $recommendation['suggested_rate']
                ]);
                Log::info("Auto-applied price for RoomType {$roomType->name} in Hotel {$hotel->id}: {$recommendation['suggested_rate']}");
            }
        }
    }
}
