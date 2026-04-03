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
        static::creating(fn ($model) => $model->slug = $model->slug ?? \Illuminate\Support\Str::slug($model->name));
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
