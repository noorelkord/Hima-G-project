<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
    'host_id',
    'governorate_id',
    'city_id',
    'neighborhood_id',
    'street',
    'title',
    'description',
    'type',
    'price',
    'area_m2',
    'rooms',
    'damage_status',
    'has_water',
    'has_electricity',
    'is_ready',
    'status',
    'rejection_reason',
    'availability',
    ];

    protected $casts = [
        'has_water'       => 'boolean',
        'has_electricity' => 'boolean',
        'is_ready'        => 'boolean',
        'price'           => 'decimal:2',
        'area_m2'         => 'decimal:2',
    ];

    // Relationship: property belongs to a host
    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    // Scope: only accepted + available properties (for public search)
    public function scopePublic($query)
    {
        return $query->where('status', 'accepted')
                     ->where('availability', 'available');
    }

    // Auto-generate full location string
    public function getFullLocationAttribute(): string
    {
        $parts = [];
        if ($this->governorate) $parts[] = $this->governorate->name;
        if ($this->city)        $parts[] = $this->city->name;
        if ($this->neighborhood) $parts[] = $this->neighborhood->name;
        if ($this->street)      $parts[] = $this->street;
        return implode(' - ', $parts);
    }
    
    // Property has many bookings
    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }
    // Property has many images
    public function images()
    {
        return $this->hasMany(PropertyImage::class);
    }

    // Get main image
    public function mainImage()
    {
        return $this->hasOne(PropertyImage::class)->where('is_main', true);
    }

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }
    public function neighborhood()
    {
        return $this->belongsTo(Neighborhood::class);
    }
}