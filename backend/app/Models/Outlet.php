<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Outlet extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'name',
        'slug',
        'type',
        'is_active',
        'metadata',
        'bank_name',
        'account_number',
        'account_name',
    ];

    protected static function booted()
    {
        static::creating(fn ($model) => $model->slug = $model->slug ?? \Illuminate\Support\Str::slug($model->name));
    }

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'json',
    ];
}
