<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuestNotification extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'reservation_id',
        'channel', // email, whatsapp, sms
        'recipient',
        'template',
        'status', // pending, sent, failed
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
