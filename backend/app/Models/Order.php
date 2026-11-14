<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'restaurant_id',
        'order_number',
        'status',
        'cooking_started_at',
        'ready_at',
    ];

    const UPDATED_AT = null;

    protected $casts = [
        'cooking_started_at' => 'datetime',
        'ready_at' => 'datetime',
    ];

    public function restaurant()
    {
        return $this->belongsTo(Restaurant::class);
    }
}
