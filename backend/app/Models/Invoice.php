<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;

class Invoice extends BaseModel
{
    use HasFactory;
    

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'order_id',
        'folio_id',
        'invoice_number',
        'sequence_number',
        'subtotal',
        'tax_amount',
        'service_charge',
        'discount_amount',
        'total_amount',
        'amount_paid',
        'currency_code',
        'currency_symbol',
        'status',
        'due_date',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function items()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
