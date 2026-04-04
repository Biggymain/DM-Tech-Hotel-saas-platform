<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Membership extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hotel_id',
        'type',
        'price_paid',
        'starts_at',
        'expires_at',
        'status',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'price_paid' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function isActive()
    {
        return $this->status === 'active' && 
               $this->starts_at <= now() && 
               $this->expires_at >= now();
    }
}
