<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Hotel;
use App\Services\ReportingService;
use Carbon\Carbon;

class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     */
    public function handle(ReportingService $reportingService): void
    {
        $yesterday = Carbon::yesterday()->toDateString();
        
        $hotels = Hotel::where('is_active', true)->get();

        foreach ($hotels as $hotel) {
            try {
                $reportingService->generateNightlyReports($hotel->id, $yesterday);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Failed generating nightly report for hotel {$hotel->id}: " . $e->getMessage());
            }
        }
    }
}
