<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'guest_id',
        'folio_id',
        'payment_gateway',
        'gateway_transaction_id',
        'amount',
        'currency',
        'status',
        'payment_source',
        'context_metadata',
        'processed_at',
    ];

    protected $casts = [
        'context_metadata' => 'array',
        'processed_at' => 'datetime',
        'amount' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function guest()
    {
        return $this->belongsTo(Guest::class);
    }

    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }
}
