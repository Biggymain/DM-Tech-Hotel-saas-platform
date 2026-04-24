<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


/**
 * Hotel is the root tenant model in this SaaS.
 * It uses the Tenantable trait to ensure isolation:
 *  - Group Admins only see hotels in their group.
 *  - Branch staff only see their specific hotel.
 */
class Hotel extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'domain', 'hotel_group_id', 'subscription_plan_id', 'subscription_tier_id',
        'email', 'phone', 'address',
        'is_active', 'currency_id', 'reservation_deadline_hours_before_checkin',
        'reservation_grace_hours', 'no_show_penalty_type',
        'bank_name', 'account_number', 'account_name', 'pos_terminal_id', 'stakeholder_emails',
        'ota_token',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($hotel) {
            if (empty($hotel->slug)) {
                $hotel->slug = \Illuminate\Support\Str::slug($hotel->name);
            }
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
        'phone' => 'encrypted',
        'address' => 'encrypted',
        'stakeholder_emails' => 'array',
        'ota_token' => 'encrypted',
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

    public function activeSubscription()
    {
        return $this->hasOne(HotelSubscription::class)->whereIn('status', ['active', 'trial', 'grace_period', 'suspended']);
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

    public function websiteOverride()
    {
        return $this->hasOne(HotelWebsiteOverride::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function outlets()
    {
        return $this->hasMany(Outlet::class);
    }

    public function tier()
    {
        return $this->belongsTo(SubscriptionTier::class, 'subscription_tier_id');
    }

    /**
     * Checks if the hotel's current subscription tier includes a specific feature.
     */
    public function hasFeature(string $feature): bool
    {
        // 1. Check New Branch Tier
        if ($this->tier) {
            return in_array($feature, $this->tier->features ?? []);
        }

        // 2. Legacy Fallback
        $subscription = $this->subscription;
        if ($subscription && $subscription->isActive()) {
            if (!$subscription->current_period_end || !$subscription->current_period_end->isPast()) {
                return in_array($feature, $subscription->plan->features ?? []);
            }
        }

        return false;
    }

    /**
     * Generates a new OTA integration key for external booking engines.
     */
    public function generateOtaIntegrationKey(): string
    {
        $token = \Illuminate\Support\Str::random(64);
        $this->update(['ota_token' => $token]);
        return $token;
    }
}
