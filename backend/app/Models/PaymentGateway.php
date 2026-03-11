<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class PaymentGateway extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'gateway_name',
        'api_key',
        'api_secret',
        'webhook_secret',
        'contract_code',
        'payment_mode',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
