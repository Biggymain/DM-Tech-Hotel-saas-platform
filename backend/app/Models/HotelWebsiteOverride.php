<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelWebsiteOverride extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'template_id',
        'custom_title',
        'custom_description',
        'custom_about_text',
        'primary_image_url',
        'secondary_image_url',
        'use_group_branding',
        'design_settings',
    ];

    protected $casts = [
        'template_id'        => 'integer',
        'use_group_branding' => 'boolean',
        'design_settings'    => 'array',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
