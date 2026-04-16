<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    

    protected $fillable = [
        'hotel_id', 'outlet_id', 'user_id', 'action', 
        'model_type', 'model_id', 'description', 
        'metadata', 'ip_address', 'device', 'severity'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
