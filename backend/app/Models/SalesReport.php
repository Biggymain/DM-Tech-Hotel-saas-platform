<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'report_date',
        'report_type',
        'total_revenue',
        'total_orders',
        'total_tax',
        'total_service_charge',
        'currency_code',
        'currency_symbol',
    ];
    
    protected $casts = [
        'report_date' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(SalesReportItem::class);
    }
}
