<?php

namespace App\Models;

use App\Models\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class LeisureAccessLog extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'outlet_id',
        'entry_time',
        'method',
        'code',
        'allow',
    ];

    protected $casts = [
        'entry_time' => 'datetime',
        'allow' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }
}
