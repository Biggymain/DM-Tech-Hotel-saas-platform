<?php

namespace App\Services;

use App\Models\RoomType;
use App\Models\RatePlan;
use App\Models\Room;
use App\Events\RoomPriceCalculated;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PricingService
{
    protected $activityLogService;

    public function __construct(ActivityLogService $activityLogService)
    {
        $this->activityLogService = $activityLogService;
    }

    public function calculateRoomPrice(RoomType $roomType, Carbon $date, ?RatePlan $ratePlan = null)
    {
        $hotelId = app()->bound('tenant_id') ? app('tenant_id') : $roomType->hotel_id;
        
        if (!$ratePlan) {
            // Find default active rate plan for this room type
            $ratePlan = $roomType->ratePlans()
                ->where('is_active', true)
                ->where(function($q) use ($date) {
                    $q->whereNull('valid_from')->orWhere('valid_from', '<=', $date);
                })
                ->where(function($q) use ($date) {
                    $q->whereNull('valid_until')->orWhere('valid_until', '>=', $date);
                })
                ->first();
        }

        if (!$ratePlan) {
            return $roomType->base_price;
        }

        $cacheKey = "pricing:{$hotelId}:{$roomType->id}:{$ratePlan->id}:{$date->toDateString()}";

        return Cache::remember($cacheKey, now()->addMinutes(10), function () use ($roomType, $date, $ratePlan, $hotelId) {
            // 2. Start with base_price from room_type_rate_plan or fallback to RoomType base price
            // The pivot table value is accessible if loaded via the relationship
            $roomTypeRatePlan = $ratePlan->roomTypes()->where('room_type_id', $roomType->id)->first();
            $basePrice = $roomTypeRatePlan ? $roomTypeRatePlan->pivot->base_price : $roomType->base_price;
            
            $finalPrice = $basePrice;

            // Apply base rate plan modifier
            $finalPrice += $ratePlan->base_price_modifier;

            // 3. Apply SeasonalRate modifier if date in range AND days_of_week matches
            $seasonalRate = $ratePlan->seasonalRates()
                ->where('start_date', '<=', $date->toDateString())
                ->where('end_date', '>=', $date->toDateString())
                ->first();

            if ($seasonalRate) {
                $daysOfWeek = $seasonalRate->days_of_week;
                $currentDayCode = strtolower($date->shortEnglishDayOfWeek); // e.g., 'fri'
                
                if (!$daysOfWeek || in_array($currentDayCode, $daysOfWeek) || in_array(strtolower($date->englishDayOfWeek), $daysOfWeek)) {
                    $finalPrice += $seasonalRate->price_modifier;
                }
            }

            // 4. Calculate hotel occupancy
            $totalRooms = Room::where('hotel_id', $hotelId)->count();
            if ($totalRooms > 0) {
                // Occupied rooms
                $occupiedRooms = Room::where('hotel_id', $hotelId)->where('status', 'occupied')->count();
                $occupancyPercentage = ($occupiedRooms / $totalRooms) * 100;

                // 5. Apply occupancy rules
                $occupancyRules = $ratePlan->occupancyRules()
                    ->where('occupancy_threshold', '<=', $occupancyPercentage)
                    ->orderBy('occupancy_threshold', 'desc')
                    ->first();

                if ($occupancyRules) {
                    $finalPrice += ($finalPrice * ($occupancyRules->price_modifier_percentage / 100));
                }
            }

            // 6. Clamp final price
            if ($ratePlan->min_price !== null) {
                $finalPrice = max($ratePlan->min_price, $finalPrice);
            }
            if ($ratePlan->max_price !== null) {
                $finalPrice = min($ratePlan->max_price, $finalPrice);
            }

            // Fire event
            event(new RoomPriceCalculated($roomType, $date, $ratePlan, $finalPrice));

            // Log activity
            $this->activityLogService->logSystemEvent(
                $hotelId,
                'price_calculated',
                "Calculated dynamic price: {$finalPrice} for RoomType ID: {$roomType->id} on {$date->toDateString()} using RatePlan: {$ratePlan->name}"
            );

            return $finalPrice;
        });
    }
}
