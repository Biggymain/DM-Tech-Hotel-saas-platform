<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'subscription_id',
        'amount',
        'currency',
        'status',
        'payment_gateway',
        'payment_reference',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function subscription()
    {
        return $this->belongsTo(HotelSubscription::class, 'subscription_id');
    }
}
