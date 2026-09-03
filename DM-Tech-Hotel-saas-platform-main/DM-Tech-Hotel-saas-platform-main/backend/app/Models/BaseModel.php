<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\TenantIsolation;

abstract class BaseModel extends Model
{
    use TenantIsolation;
    
    // Core abstraction to standardize tenant global scopes out of the box.
}
