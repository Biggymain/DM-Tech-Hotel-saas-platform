<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class StaffDailyPin extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'hotel_id',
        'pin_hash',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'pin_hash' => 'hashed',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
