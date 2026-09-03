<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SalesReportItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_report_id',
        'outlet_id',
        'menu_item_id',
        'item_name',
        'category_name',
        'quantity_sold',
        'amount',
    ];

    public function report()
    {
        return $this->belongsTo(SalesReport::class, 'sales_report_id');
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }
}
