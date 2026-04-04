<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MenuItem extends BaseModel
{
    use HasFactory;
    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'menu_category_id',
        'department_id',
        'name',
        'description',
        'price',
        'cost_price',
        'image_url',
        'is_available',
        'is_active',
        'display_order',
        'station_id',
        'station_name',
        'kitchen_station_id',
    ];

    public function kitchenStation()
    {
        return $this->belongsTo(KitchenStation::class);
    }

    protected $casts = [
        'price' => 'decimal:2',
        'cost_price' => 'decimal:2',
        'is_available' => 'boolean',
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

    public function menuCategory()
    {
        return $this->belongsTo(MenuCategory::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function modifiers()
    {
        return $this->belongsToMany(Modifier::class, 'menu_item_modifiers', 'menu_item_id', 'modifier_id')->withTimestamps();
    }

    public function ingredients()
    {
        return $this->hasMany(MenuItemIngredient::class);
    }
}
