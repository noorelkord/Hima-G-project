<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contract extends Model
{
    use HasFactory, SoftDeletes;

   protected $fillable = [
    'booking_id',
    'tenant_id',
    'host_id',
    'property_id',
    'start_date',
    'end_date',
    'price',
    'status',
    'pdf_path',
    'closed_at',
    'expiry_reminder_date',
    'expiry_reminder_sent',
];

    protected $casts = [
    'start_date'           => 'date:Y-m-d', // يوم تقويمي — لا علاقة بالتوقيت
    'end_date'             => 'date:Y-m-d', // يوم تقويمي — لا علاقة بالتوقيت
    'expiry_reminder_date' => 'date:Y-m-d', // يوم تقويمي — لا علاقة بالتوقيت
    'expiry_reminder_sent' => 'boolean',
    'closed_at'            => 'datetime',   // لحظة زمنية — يُحفظ UTC ✅
    'price'                => 'decimal:2',
];

    // Relationships
    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function tenant()
    {
        return $this->belongsTo(User::class, 'tenant_id');
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
    // Contract has reviews
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}