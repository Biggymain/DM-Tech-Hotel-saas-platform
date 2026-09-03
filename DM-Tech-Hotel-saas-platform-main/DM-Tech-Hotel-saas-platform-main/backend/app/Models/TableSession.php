<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableSession extends Model
{
    protected $fillable = [
        'hotel_id',
        'branch_id',
        'tenant_id',
        'outlet_id',
        'table_number',
        'status',
        'opened_at',
        'closed_at',
        'opened_by_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function guests()
    {
        return $this->hasMany(TableSessionGuest::class);
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }
}
