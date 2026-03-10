<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Role extends Model
{
    use Tenantable;

    protected $fillable = ['hotel_id', 'name', 'slug', 'is_system_role'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }
}
