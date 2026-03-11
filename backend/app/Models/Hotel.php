<?php

namespace App\Models;

use App\Traits\Tenantable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Hotel extends Model
{
    use HasFactory, SoftDeletes, Tenantable;

    protected $fillable = [
        'name', 'domain', 'subscription_plan_id', 'email', 'phone', 'address', 'is_active', 'currency_id',
        'reservation_deadline_hours_before_checkin', 'reservation_grace_hours', 'no_show_penalty_type'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function subscription()
    {
        return $this->hasOne(HotelSubscription::class);
    }

    public function hasActiveSubscription()
    {
        return $this->subscription && $this->subscription->isActive();
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    public function roomTypes()
    {
        return $this->hasMany(RoomType::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    public function settings()
    {
        return $this->hasMany(HotelSetting::class);
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }
}
