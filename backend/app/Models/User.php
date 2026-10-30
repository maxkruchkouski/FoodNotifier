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
        'password_hash', // Перенесено из монолита
        'role',          // superadmin или owner
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

// Указываем Laravel, какое поле использовать в качестве пароля для авторизации
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Магический геттер для совместимости с Laravel Sanctum и дефолтными Guard.
     * Возвращает password_hash, когда ядро фреймворка запрашивает скрытое свойство ->password
     */
    public function getPasswordAttribute()
    {
        return $this->password_hash;
    }

    // Связь: у одного пользователя (owner) может быть много ресторанов
    public function restaurants()
    {
        return $this->hasMany(Restaurant::class, 'owner_id');
    }
}
