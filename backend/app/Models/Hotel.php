<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Hotel is the root tenant model in this SaaS.
 * It must NOT use the Tenantable trait because it IS the tenant —
 * scoping it by hotel_id would create circular filtering and break
 * registration/login on an empty database.
 */
class Hotel extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'domain', 'hotel_group_id', 'subscription_plan_id', 'email', 'phone', 'address',
        'is_active', 'currency_id', 'reservation_deadline_hours_before_checkin',
        'reservation_grace_hours', 'no_show_penalty_type',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /** The Organization (HotelGroup) this branch belongs to. */
    public function group()
    {
        return $this->belongsTo(HotelGroup::class, 'hotel_group_id');
    }

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
