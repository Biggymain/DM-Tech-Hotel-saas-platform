<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Tenantable;

class Supplier extends Model
{
    use Tenantable, SoftDeletes;

    protected $fillable = [
        'hotel_id', 'name', 'contact_name', 'email', 'phone', 'address', 'status'
    ];
}
