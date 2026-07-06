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
        $requiredNames = [
            $this->second_name,
            $this->third_name,
            $this->last_name,
        ];

        foreach ($requiredNames as $name) {
            if (trim((string) $name) === '') {
                return false;
            }
        }

        return preg_match('/^[0-9]{9}$/', (string) $this->national_id) === 1
            && preg_match('/^\+(970|972)[0-9]{9}$/', (string) $this->phone) === 1;
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
