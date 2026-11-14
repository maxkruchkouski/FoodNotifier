<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class Restaurant extends Model
{
    protected $fillable = ['name', 'slug', 'owner_id', 'is_active'];

    protected static function booted(): void
    {
        static::addGlobalScope('owner', function (Builder $builder) {
            $user = Auth::user();

            if (!$user) {
                return;
            }

            // Суперадмин видит все рестораны
            if ($user->is_superadmin) {
                return;
            }

            // Owner видит только свои
            $builder->where('owner_id', $user->id);
        });
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
