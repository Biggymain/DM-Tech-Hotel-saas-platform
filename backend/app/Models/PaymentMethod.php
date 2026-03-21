<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenantable;

class PaymentMethod extends Model
{
    use SoftDeletes, Tenantable;

    protected $fillable = [
        'hotel_id',
        'name',
        'is_active',
        'requires_reference',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'requires_reference' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
