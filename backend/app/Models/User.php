<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $fillable = [
        'first_name',
        'second_name',
        'third_name',
        'last_name',
        'national_id',
        'email',
        'password',
        'phone',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // هل المستخدم أكمل بياناته؟
    public function isProfileComplete(): bool
    {
        return !empty($this->second_name)
            && !empty($this->third_name)
            && !empty($this->last_name)
            && !empty($this->national_id)
            && !empty($this->phone);
    }

    public function isHostReady(): bool
    {
        return $this->isProfileComplete();
    }

    public function isTenantReady(): bool
    {
        return $this->isProfileComplete();
    }

}