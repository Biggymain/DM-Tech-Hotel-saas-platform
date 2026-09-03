<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModifierOption extends BaseModel
{
    

    protected $fillable = [
        'hotel_id',
        'modifier_id',
        'name',
        'price_adjustment',
        'is_active',
        'display_order',
    ];

    protected $casts = [
        'price_adjustment' => 'decimal:2',
        'is_active' => 'boolean',
        'display_order' => 'integer',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }
}
