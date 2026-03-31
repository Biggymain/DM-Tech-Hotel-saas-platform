<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\TenantIsolation;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TenantIsolation;

    protected $fillable = [
        'name',
        'email',
        'password',
        'pin_code',
        'hotel_id',
        'hotel_group_id',
        'group_id',
        'outlet_id',
        'role',
        'is_on_duty',
        'last_duty_toggle_at',
        'is_super_admin',
        'must_change_password',
        'kitchen_station_id',
    ];

    public function kitchenStation()
    {
        return $this->belongsTo(KitchenStation::class);
    }

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_on_duty' => 'boolean',
            'last_duty_toggle_at' => 'datetime',
            'must_change_password' => 'boolean',
        ];
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    /**
     * The HotelGroup (Organization) this user administers.
     * Only set for GROUP_ADMIN users — branch staff use hotel_id instead.
     */
    public function hotelGroup()
    {
        return $this->belongsTo(HotelGroup::class, 'hotel_group_id');
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function hasRole(string $role): bool
    {
        return $this->roles()->withoutGlobalScopes()->where('slug', $role)->exists();
    }

    /** True when user manages all branches of an Organization. */
    public function isGroupAdmin(): bool
    {
        return !empty($this->hotel_group_id) && empty($this->hotel_id);
    }

    public function isKitchenManager(): bool
    {
        return $this->hasRole('kitchen-manager');
    }

    public function isGeneralManager(): bool
    {
        return $this->hasRole('general-manager');
    }

    public function isBranchManager(): bool
    {
        return $this->hasRole('hotelowner') || $this->hasRole('general-manager');
    }

    public function isOutletManager(): bool
    {
        return $this->hasRole('outlet-manager');
    }

    public function scopeOnDuty($query)
    {
        return $query->where('is_on_duty', true);
    }

    public function scopeByOutlet($query, $outletId)
    {
        return $query->where('outlet_id', $outletId);
    }
}
