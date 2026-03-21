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
        'type',
        'tables_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
