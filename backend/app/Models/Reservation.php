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
        'reservation_number',
        'adults',
        'children',
        'total_amount',
        'special_requests',
        'source',
        'payment_reference',
        'parent_id',
    ];

    protected $casts = [
        'check_in_date'  => 'date',
        'check_out_date' => 'date',
        'total_amount'   => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function hotel()     { return $this->belongsTo(Hotel::class); }
    public function rooms() { return $this->belongsToMany(Room::class, 'reservation_rooms')->withPivot('rate'); }
    public function room()      { return $this->belongsTo(Room::class); }
    public function guest()     { return $this->belongsTo(Guest::class); }
    public function folios()    { return $this->hasMany(Folio::class); }
    public function folio()     { return $this->hasOne(Folio::class); }
    public function digitalKeys() { return $this->hasMany(DigitalKey::class); }

    // ─── Check-in trigger ─────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::creating(function (Reservation $reservation) {
            if (empty($reservation->reservation_number)) {
                do {
                    $number = 'RSV-' . date('Y') . '-' . strtoupper(substr(uniqid(), -5));
                    $exists = static::where('hotel_id', $reservation->hotel_id)
                        ->where('reservation_number', $number)
                        ->exists();
                } while ($exists);
                $reservation->reservation_number = $number;
            }
        });

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
