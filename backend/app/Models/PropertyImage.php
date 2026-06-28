<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PropertyImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'property_id',
        'image_path',
        'is_main',
    ];

    protected $casts = [
        'is_main' => 'boolean',
    ];

    // Auto-generate full URL for image_path
    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->image_path);
    }
    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}