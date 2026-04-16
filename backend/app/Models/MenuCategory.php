<?php

namespace App\Models;


class MenuCategory extends BaseModel
{
    

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'name',
        'station',
        'description',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function items()
    {
        return $this->hasMany(MenuItem::class);
    }
}
