<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewWindow extends Model
{
    protected $fillable = [
        'contract_id',
        'user_id',
        'role',
        'status',
        'reminders_sent',
        'last_reminded_at',
    ];

    protected $casts = [
        'last_reminded_at' => 'datetime',
    ];

    // العلاقات
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Scopes مفيدة
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeReadyForReminder($query, $reminderDays)
    {
        return $query->where(function($q) use ($reminderDays) {
            $q->whereNull('last_reminded_at')
              ->orWhere('last_reminded_at', '<=', now()->subDays($reminderDays));
        });
    }
}