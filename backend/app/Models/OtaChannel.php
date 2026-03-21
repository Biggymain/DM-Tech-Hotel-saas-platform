<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtaChannel extends Model
{
    protected $fillable = ['name', 'provider', 'api_endpoint', 'is_active'];

    public function connections()
    {
        return $this->hasMany(HotelChannelConnection::class);
    }
}
