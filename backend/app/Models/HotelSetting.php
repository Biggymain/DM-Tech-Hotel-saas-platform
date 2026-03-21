<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    protected $fillable = ['hotel_id', 'key', 'value', 'type'];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
