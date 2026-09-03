<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'username',
        'is_onboarded',
        'loyalty_points',
        'identification_type',
        'identification_number',
        'id_scan_url',
    ];

    protected $casts = [
        'first_name' => 'encrypted',
        'last_name' => 'encrypted',
        'email' => 'encrypted',
        'phone' => 'encrypted',
        'username' => 'encrypted',
        'is_onboarded' => 'boolean',
        'loyalty_points' => 'integer',
        'identification_type' => 'encrypted',
        'identification_number' => 'encrypted',
        'id_scan_url' => 'encrypted',
    ];

    protected static function booted()
    {
        static::saving(function ($guest) {
            $secret = config('app.key');
            if (!empty($guest->email)) {
                $guest->email_bidx = hash_hmac('sha256', strtolower(trim($guest->email)), $secret);
            }
            if (!empty($guest->phone)) {
                $guest->phone_bidx = hash_hmac('sha256', trim($guest->phone), $secret);
            }
            if (!empty($guest->username)) {
                $guest->username_bindex = hash_hmac('sha256', trim($guest->username), $secret);
            }
        });
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
    
    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }
}
