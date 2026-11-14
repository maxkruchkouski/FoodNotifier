<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Суперадмин
        User::create([
            'name' => 'Superadmin',
            'email' => 'admin@foodnotifier.by',
            'password_hash' => Hash::make('password'),
            'role' => 'superadmin',
            'is_superadmin' => true,
        ]);

        // Тестовый owner + ресторан
        $owner = User::create([
            'name' => 'Test Owner',
            'email' => 'owner@test.by',
            'password_hash' => Hash::make('password'),
            'role' => 'owner',
        ]);

        Restaurant::create([
            'name' => 'Кофейня у дома',
            'slug' => 'coffee-house',
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);
    }
}
