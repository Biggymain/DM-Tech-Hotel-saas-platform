<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'hotel_id',
        'outlet_id',
        'name',
        'slug',
        'is_active',
    ];

    protected static function booted()
    {
        static::creating(fn ($model) => $model->slug = $model->slug ?? \Illuminate\Support\Str::slug($model->name));
    }

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function outlet()
    {
        return $this->belongsTo(Outlet::class);
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'department_permissions', 'department_id', 'permission_id')
                    ->withPivot('hotel_id')
                    ->withTimestamps();
    }
}
