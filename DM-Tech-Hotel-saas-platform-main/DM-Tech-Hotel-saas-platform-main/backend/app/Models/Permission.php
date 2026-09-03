<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
// Removed Tenantable because system permissions are shared across all hotels and have hotel_id = null

class Permission extends Model
{
    protected $fillable = ['hotel_id', 'name', 'slug', 'module'];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }

    public function departments()
    {
        return $this->belongsToMany(Department::class, 'department_permissions', 'permission_id', 'department_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }
}
