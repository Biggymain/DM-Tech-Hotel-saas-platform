<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelSetting extends Model
{
    protected $fillable = ['hotel_id', 'setting_key', 'setting_value', 'type', 'use_internal_website'];

    protected $casts = [
        'use_internal_website' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
