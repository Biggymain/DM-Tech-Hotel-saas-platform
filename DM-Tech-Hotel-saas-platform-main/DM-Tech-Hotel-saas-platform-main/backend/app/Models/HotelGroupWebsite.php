<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HotelGroupWebsite extends Model
{
    use HasFactory;

    protected $fillable = [
        'hotel_group_id',
        'slug',
        'title',
        'description',
        'about_text',
        'logo_url',
        'banner_url',
        'primary_color',
        'secondary_color',
        'social_links',
        'features',
        'email',
        'phone',
        'address',
        'design_settings',
        'is_active',
    ];

    protected $casts = [
        'social_links' => 'array',
        'features'        => 'array',
        'design_settings' => 'array',
        'is_active'       => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(HotelGroup::class, 'hotel_group_id');
    }
}
