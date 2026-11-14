<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'is_superadmin',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_superadmin' => 'boolean',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function getPasswordAttribute()
    {
        return $this->password_hash;
    }

    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }
}
