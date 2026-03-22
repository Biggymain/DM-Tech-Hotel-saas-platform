<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableSessionGuest extends Model
{
    protected $fillable = [
        'table_session_id',
        'guest_id',
        'guest_portal_session_id',
        'system_guest_name',
        'waitress_custom_alias',
        'has_paid',
    ];

    protected $casts = [
        'has_paid' => 'boolean',
    ];

    public function session()
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }
}
