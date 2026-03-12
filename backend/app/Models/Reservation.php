<?php

namespace App\Models;

use App\Jobs\GenerateDigitalKeyJob;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Tenantable;

class Reservation extends Model
{
    use HasFactory, Tenantable;

    protected $fillable = [
        'hotel_id',
        'room_id',
        'guest_id',
        'check_in_date',
        'check_out_date',
        'status',
        'adults',
        'children',
        'total_amount',
        'special_requests',
        'source',
        'payment_reference',
    ];

    protected $casts = [
        'check_in_date'  => 'date',
        'check_out_date' => 'date',
        'total_amount'   => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function hotel()     { return $this->belongsTo(Hotel::class); }
    public function room()      { return $this->belongsTo(Room::class); }
    public function guest()     { return $this->belongsTo(Guest::class); }
    public function folio()     { return $this->hasOne(Folio::class); }
    public function digitalKeys() { return $this->hasMany(DigitalKey::class); }

    // ─── Check-in trigger ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::updated(function (Reservation $reservation) {
            // When status transitions to 'checked_in', generate the digital door key
            if (
                $reservation->isDirty('status') &&
                $reservation->status === 'checked_in' &&
                $reservation->getOriginal('status') !== 'checked_in'
            ) {
                GenerateDigitalKeyJob::dispatch($reservation)->onQueue('lock-keys');
            }
        });
    }
}
