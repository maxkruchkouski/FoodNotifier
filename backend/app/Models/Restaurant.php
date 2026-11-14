<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class Restaurant extends Model
{
    protected $fillable = ['name', 'slug', 'owner_id', 'subscription_status'];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            if (Auth::check()) {
                $user = Auth::user();
                // Если пользователь НЕ суперадмин, фильтруем по owner_id
                if ($user->email !== 'admin@foodnotifier.by') {
                    $builder->where('owner_id', $user->id);
                }
            }
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
