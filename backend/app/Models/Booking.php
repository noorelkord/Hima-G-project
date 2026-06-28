<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'property_id',
        'start_date',
        'end_date',
        'price',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        'price'      => 'decimal:2',
    ];

    // Booking belongs to a tenant
    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    // Booking belongs to a property
    public function property()
    {
        return $this->belongsTo(Property::class);
    }

    // Booking has one contract
    public function contract()
    {
        return $this->hasOne(Contract::class);
    }
}