<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'is_active',
        'owner_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Связь: Ресторан принадлежит пользователю
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
