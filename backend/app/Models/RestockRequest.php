<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestockRequest extends Model
{
    protected $fillable = [
        'hotel_id',
        'branch_id',
        'kitchen_station_id',
        'menu_item_id',
        'requested_by',
        'status',
        'notes',
    ];

    public function menuItem()
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function kitchenStation()
    {
        return $this->belongsTo(KitchenStation::class);
    }
}
