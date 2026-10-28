<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Restaurant extends Model
{
    protected $fillable = ['slug', 'name', 'owner_id', 'is_active'];
}
