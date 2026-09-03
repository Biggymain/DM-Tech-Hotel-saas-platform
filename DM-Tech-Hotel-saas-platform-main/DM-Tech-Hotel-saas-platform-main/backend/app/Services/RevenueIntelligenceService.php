<?php

namespace App\Services;

use App\Models\Hotel;
use App\Models\HotelEvent;
use App\Models\Reservation;
use App\Models\RevenueInsight;
use App\Models\Room;
use App\Models\RoomType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueIntelligenceService
{
    /**
     * Generate revenue insights for a specific hotel and date range.
     */
    public function generateInsights(Hotel $hotel, Carbon $startDate, Carbon $endDate): Collection
    {
        $period = CarbonPeriod::create($startDate, $endDate);
        $insights = collect();

        // Historical data for trend analysis (last 90 days)
        $historicalStart = $startDate->copy()->subDays(90);
        $historicalReservations = $this->getReservationsInPeriod($hotel->id, $historicalStart, $startDate->copy()->subDay());

        foreach ($period as $date) {
            $insight = $this->analyzeDate($hotel, $date, $historicalReservations);
            $insights->push($insight);
        }

        return $insights;
    }

    /**
     * Analyze a specific date and generate an insight.
     */
    protected function analyzeDate(Hotel $hotel, Carbon $date, Collection $historicalReservations): RevenueInsight
    {
        $totalRooms = $hotel->rooms()->count();
        if ($totalRooms === 0) {
            return new RevenueInsight(); // Or handle as error
        }

        // 1. Calculate Occupancy for the target date (confirmed/checked-in reservations)
        $targetReservations = $this->getReservationsOnDate($hotel->id, $date);
        
        // Count total rooms booked across these reservations
        $occupiedRooms = 0;
        foreach ($targetReservations as $res) {
            $occupiedRooms += $res->rooms()->count();
        }
        
        $occupancyRate = $totalRooms > 0 ? ($occupiedRooms / $totalRooms) * 100 : 0;

        // 2. Calculate ADR (Average Daily Rate) for the target date
        $totalRevenue = $targetReservations->sum('total_amount'); // Simplified: should be daily rate
        // In a real system, we'd calculate this based on daily room rates assigned to the reservation for that specific night.
        // For this implementation, we'll estimate based on reservation total / nights.
        $adr = $occupiedRooms > 0 ? $this->calculateADR($targetReservations) : 0;

        // 3. Calculate RevPAR (Revenue Per Available Room)
        $revpar = ($adr * $occupancyRate) / 100;

        // 4. Predict Demand Score (0-100)
        $demandScore = $this->calculateDemandScore($hotel, $date, $historicalReservations, $occupancyRate);

        // 5. Generate Pricing Recommendations
        $recommendations = $this->generateRecommendations($hotel, $demandScore, $occupancyRate);

        return RevenueInsight::updateOrCreate(
            ['hotel_id' => $hotel->id, 'date' => $date->toDateString()],
            [
                'occupancy_rate' => $occupancyRate,
                'avg_daily_rate' => $adr,
                'revpar' => $revpar,
                'demand_score' => $demandScore,
                'recommended_rate_adjustment' => $recommendations,
            ]
        );
    }

    protected function getReservationsInPeriod(int $hotelId, Carbon $start, Carbon $end): Collection
    {
        return Reservation::where('hotel_id', $hotelId)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('check_in_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('check_out_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->get();
    }

    protected function getReservationsOnDate(int $hotelId, Carbon $date): Collection
    {
        $dateStr = $date->toDateString();
        return Reservation::where('hotel_id', $hotelId)
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_in_date', '<=', $dateStr)
            ->whereDate('check_out_date', '>', $dateStr)
            ->get();
    }

    protected function calculateADR(Collection $reservations): float
    {
        $count = $reservations->count();
        if ($count === 0) return 0;

        $totalDailyRate = 0;
        foreach ($reservations as $res) {
            $nights = Carbon::parse($res->check_in_date)->diffInDays(Carbon::parse($res->check_out_date));
            $nights = $nights > 0 ? $nights : 1;
            $totalDailyRate += ($res->total_amount / $nights);
        }

        return $totalDailyRate / $count;
    }

    protected function calculateDemandScore(Hotel $hotel, Carbon $date, Collection $historicalReservations, float $currentOccupancy): int
    {
        $score = (int) $currentOccupancy;

        // Weekend boost
        if ($date->isWeekend()) {
            $score += 15;
        }

        // Seasonality (Simplified: Holiday months boost)
        $holidayMonths = [12, 1, 4, 8]; // Dec, Jan, Apr, Aug
        if (in_array($date->month, $holidayMonths)) {
            $score += 10;
        }

        // Historical Trend (Last 30 days same weekday)
        $sameWeekdayHistorical = $historicalReservations->filter(function ($res) use ($date) {
            return Carbon::parse($res->check_in_date)->dayOfWeek === $date->dayOfWeek;
        });

        if ($sameWeekdayHistorical->count() > 0) {
            $score += 5;
        }

        // Event detect
        $activeEvents = HotelEvent::where('hotel_id', $hotel->id)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->get();

        foreach ($activeEvents as $event) {
            $score += $event->getImpactBoost();
        }

        return min(100, $score);
    }

    protected function generateRecommendations(Hotel $hotel, int $demandScore, float $occupancyRate): array
    {
        $recommendations = [];
        $roomTypes = $hotel->roomTypes;

        foreach ($roomTypes as $roomType) {
            $adjustment = 0;

            if ($demandScore > 80 || $occupancyRate > 90) {
                $adjustment = 15; // 15% increase
            } elseif ($demandScore > 60 || $occupancyRate > 70) {
                $adjustment = 10; // 10% increase
            } elseif ($demandScore < 30 && $occupancyRate < 30) {
                $adjustment = -10; // 10% decrease to stimulate demand
            }

            if ($adjustment !== 0) {
                $currentRate = $roomType->base_price;
                
                // Incorporate Market Awareness (Phase 2 Enhancement)
                $marketAdjustment = $this->getMarketAdjustment($roomType);
                $finalAdjustment = $adjustment + $marketAdjustment;
                
                $newRate = $currentRate * (1 + ($finalAdjustment / 100));
                
                $recommendations[] = [
                    'room_type_id' => $roomType->id,
                    'room_type_name' => $roomType->name,
                    'current_rate' => $currentRate,
                    'suggested_rate' => round($newRate, -2), // Round to nearest 100
                    'adjustment_percent' => $finalAdjustment,
                    'reason' => $this->getRecommendationReason($demandScore, $occupancyRate, $marketAdjustment),
                    'market_aware' => $marketAdjustment != 0
                ];
            }
        }

        return $recommendations;
    }

    protected function getRecommendationReason(int $demandScore, float $occupancyRate, int $marketAdjustment = 0): string
    {
        $reason = 'Standard market adjustment based on demand forecast.';
        
        if ($occupancyRate > 90) $reason = 'Extremely high occupancy detected.';
        elseif ($demandScore > 80) $reason = 'High historical demand spike or major event detected.';
        elseif ($occupancyRate > 70) $reason = 'Healthy occupancy with upward trend.';
        elseif ($occupancyRate < 30) $reason = 'Low occupancy expected; stimulate demand with discounted rates.';
        
        if ($marketAdjustment > 0) {
            $reason .= ' Competitor pricing is currently higher than yours.';
        } elseif ($marketAdjustment < 0) {
            $reason .= ' Competitor pricing is currently lower than yours.';
        }
        
        return $reason;
    }

    /**
     * Phase 2: Market Competitor Tracker (Simulated)
     */
    protected function getMarketAdjustment(RoomType $roomType): int
    {
        // In a real system, this would fetch from an OTA price scraping service
        // For simulation, we randomly return a small market variance (-2% to +5%)
        $seed = crc32($roomType->id . date('Y-m-d'));
        srand($seed);
        $variance = rand(-2, 5);
        
        return $variance;
    }
}
