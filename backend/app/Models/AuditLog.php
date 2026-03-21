<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class AuditLog extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id', 'user_id', 'entity_type', 'entity_id',
        'change_type', 'old_values', 'new_values', 'reason', 'source',
        'ip_address', 'user_agent'
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
