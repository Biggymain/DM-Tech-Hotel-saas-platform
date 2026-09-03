<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Models\ActivityLog;
use App\Models\AuditLog;
use Carbon\Carbon;

class CleanOldLogsJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        // Delete activity logs older than 90 days
        $ninetyDaysAgo = Carbon::now()->subDays(90);
        ActivityLog::where('created_at', '<', $ninetyDaysAgo)->delete();

        // Archive/Delete audit logs older than 365 days
        $threeHundredSixtyFiveDaysAgo = Carbon::now()->subDays(365);
        AuditLog::where('created_at', '<', $threeHundredSixtyFiveDaysAgo)->delete();
    }
}
