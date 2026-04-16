<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RevenueConfig extends Model
{
    

    protected $fillable = [
        'hotel_id',
        'auto_apply_enabled',
        'rules',
    ];

    protected $casts = [
        'auto_apply_enabled' => 'boolean',
        'rules' => 'array',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
