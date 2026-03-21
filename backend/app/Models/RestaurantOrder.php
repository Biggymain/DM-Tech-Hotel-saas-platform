<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class RestaurantOrder extends Model
{
    use Tenantable;
}
