<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PushSubscription extends Model
{
    protected $fillable = [
        'restaurant_id',
        'order_number',
        'endpoint',
        'public_key',
        'auth_token',
    ];

    public function restaurant()
    {
        return $this->belongsTo(\App\Models\Restaurant::class);
    }
}
