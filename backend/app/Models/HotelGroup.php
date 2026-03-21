<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * HotelGroup represents an Organization (e.g. "DM Tech Hotels Group").
 * A HotelGroup can own multiple Hotel branches.
 * GROUP_ADMIN users are linked to a HotelGroup and can see ALL branches within it.
 *
 * NOTE: HotelGroup is NOT a tenant model — it sits ABOVE the tenant layer.
 * It must NOT use the Tenantable trait.
 */
class HotelGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'contact_email',
        'country',
        'currency',
        'tax_rate',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'tax_rate'  => 'decimal:2',
    ];

    /**
     * All hotel branches owned by this group.
     */
    public function branches()
    {
        return $this->hasMany(Hotel::class, 'hotel_group_id');
    }

    /**
     * All users who are GROUP_ADMINs for this group.
     */
    public function admins()
    {
        return $this->hasMany(User::class, 'hotel_group_id');
    }

    /**
     * Convenience: get all branch IDs for use in TenantScope.
     */
    /**
     * The public website configuration for this group.
     */
    public function website()
    {
        return $this->hasOne(HotelGroupWebsite::class, 'hotel_group_id');
    }
}
