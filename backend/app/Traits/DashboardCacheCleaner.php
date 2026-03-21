<?php

namespace App\Traits;

use Illuminate\Support\Facades\Cache;

trait DashboardCacheCleaner
{
    public static function booted()
    {
        static::saved(function ($model) {
            static::clearDashboardCache($model);
        });

        static::deleted(function ($model) {
            static::clearDashboardCache($model);
        });
    }

    protected static function clearDashboardCache($model)
    {
        if (isset($model->hotel_id)) {
            Cache::forget("dashboard_occupancy_{$model->hotel_id}");
            Cache::forget("dashboard_revenue_{$model->hotel_id}");
            Cache::forget("dashboard_operations_{$model->hotel_id}");
        }
    }
}
