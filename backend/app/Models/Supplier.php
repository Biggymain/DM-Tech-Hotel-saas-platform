<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends BaseModel
{
    use  SoftDeletes;

    protected $fillable = [
        'hotel_id', 'name', 'contact_name', 'email', 'phone', 'address', 'status'
    ];
}
