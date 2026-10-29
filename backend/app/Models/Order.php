<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'restaurant_id', // Хранит slug ресторана
        'order_number',
        'status',
        'cooking_started_at',
        'ready_at',
    ];

    // В монолите нет поля updated_at, отключаем автоматическое обновление
    const UPDATED_AT = null;

    protected $casts = [
        'cooking_started_at' => 'datetime',
        'ready_at' => 'datetime',
    ];
}
