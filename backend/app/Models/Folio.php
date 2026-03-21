<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Folio extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'status',
        'currency',
        'total_charges',
        'total_payments',
        'balance',
    ];

    protected $casts = [
        'total_charges' => 'decimal:2',
        'total_payments' => 'decimal:2',
        'balance' => 'decimal:2',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
    
    public function items()
    {
        return $this->hasMany(FolioItem::class);
    }
}
