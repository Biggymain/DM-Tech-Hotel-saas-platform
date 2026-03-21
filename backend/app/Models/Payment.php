<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Payment extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'invoice_id',
        'payment_method_id',
        'type',
        'amount',
        'transaction_reference',
        'status',
        'processed_by_id',
        'notes',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by_id');
    }
}
