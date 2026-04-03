<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Outlet extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'name',
        'slug',
        'type',
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(function ($outlet) {
            if (!$outlet->slug) {
                $outlet->slug = \Illuminate\Support\Str::slug($outlet->name);
            }
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
