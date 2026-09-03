<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'branch_id',
        'outlet_id',
        'model_type',
        'model_id',
        'action',
        'payload',
        'version',
        'user_id',
        'device_id',
        'status',
        'synced_at',
        'attempts',
        'last_error',
    ];

    protected $casts = [
        'payload' => 'array',
        'version' => 'datetime',
        'synced_at' => 'datetime',
    ];
}
