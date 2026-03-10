<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Department extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'department_permissions', 'department_id', 'permission_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }
}
