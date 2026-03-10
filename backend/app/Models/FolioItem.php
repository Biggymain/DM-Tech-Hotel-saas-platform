<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FolioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'folio_id',
        'attachable_type',
        'attachable_id',
        'description',
        'amount',
        'is_charge',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_charge' => 'boolean',
    ];

    public function folio()
    {
        return $this->belongsTo(Folio::class);
    }

    public function attachable()
    {
        return $this->morphTo();
    }
}
