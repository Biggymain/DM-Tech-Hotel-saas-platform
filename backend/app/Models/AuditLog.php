<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id', 'user_id', 'entity_type', 'entity_id',
        'change_type', 'old_values', 'new_values', 'reason', 'source',
        'ip_address', 'user_agent', 'severity_score'
    ];

    /**
     * Ensure entity_id is never NULL to satisfy strict SQLite database constraints.
     */
    public function setEntityIdAttribute($value)
    {
        $this->attributes['entity_id'] = $value ?? 0;
    }

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
