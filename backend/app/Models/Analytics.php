<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    protected $table = 'analytics';

    protected $fillable = [
        'restaurant_id',
    ];

    // В этой таблице есть только created_at, отключаем автоматические timestamps Laravel
    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();
        // Автоматически заполняем created_at текущим временем при создании записи
        static::creating(function ($model) {
            $model->created_at = $model->freshTimestamp();
        });
    }
}
