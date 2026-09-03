<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KitchenStation extends Model
{
    protected $fillable = [
        'hotel_id',
        'branch_id',
        'name',
        'slug',
        'description',
        'is_active',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function menuItems()
    {
        return $this->hasMany(MenuItem::class);
    }

    public function tickets()
    {
        return $this->hasMany(KitchenTicket::class);
    }

    public function staff()
    {
        return $this->hasMany(User::class);
    }
}
