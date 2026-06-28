<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'governorate_id', 
        'name'
    ];

    public function governorate()
    {
        return $this->belongsTo(Governorate::class);
    }

    public function neighborhoods()
    {
        return $this->hasMany(Neighborhood::class);
    }
    public function properties()
    {
        return $this->hasMany(Property::class);
    }
}