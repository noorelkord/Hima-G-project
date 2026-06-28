<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_id',
        'reviewer_id',
        'reviewee_id',
        'property_id',
        'rating',
        'comment',
        'type',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    // Relationships
    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function reviewee()
    {
        return $this->belongsTo(User::class, 'reviewee_id');
    }

    public function property()
    {
        return $this->belongsTo(Property::class);
    }
}