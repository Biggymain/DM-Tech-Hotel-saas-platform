<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GatewaySetting extends Model
{
    protected $fillable = [
        'hotel_id',
        'gateway_name',
        'api_key',
        'secret_key',
        'contract_code',
        'is_active',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'secret_key' => 'encrypted',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'api_key',
        'secret_key',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
