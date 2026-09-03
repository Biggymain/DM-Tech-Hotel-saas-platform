<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Modifier extends BaseModel
{
    

    protected $fillable = [
        'hotel_id',
        'name',
        'description',
        'min_selections',
        'max_selections',
        'is_active',
    ];

    protected $casts = [
        'min_selections' => 'integer',
        'max_selections' => 'integer',
        'is_active' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function modifierOptions()
    {
        return $this->hasMany(ModifierOption::class);
    }

    public function menuItems()
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_modifiers', 'modifier_id', 'menu_item_id')->withTimestamps();
    }
}
