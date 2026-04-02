<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use App\Traits\Tenantable;

class KitchenTicketStatusHistory extends Model
{
    use Tenantable;

    protected $fillable = [
        'hotel_id',
        'kitchen_ticket_id',
        'previous_status',
        'new_status',
        'changed_by_user_id',
    ];

    public function ticket()
    {
        return $this->belongsTo(KitchenTicket::class, 'kitchen_ticket_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
