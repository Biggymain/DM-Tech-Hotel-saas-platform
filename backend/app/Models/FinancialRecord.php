<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class FinancialRecord extends Model
{
    use Tenantable;
}
