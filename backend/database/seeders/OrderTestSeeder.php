<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class OrderTestSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::withoutGlobalScopes()->where('slug', 'coffee-house')->first();
        if (! $restaurant) {
            $this->command->warn('coffee-house не найден — пропускаем.');
            return;
        }

        $created = 0;
        for ($d = 13; $d >= 0; $d--) {
            $ordersPerDay = rand(3, 8);
            for ($i = 0; $i < $ordersPerDay; $i++) {
                $createdAt = Carbon::now()
                    ->subDays($d)
                    ->setTime(rand(8, 21), rand(0, 59), rand(0, 59));

                $status = $d === 0
                    ? ['pending', 'cooking', 'ready'][array_rand([0, 1, 2])]
                    : 'completed';

                $cookingStarted = in_array($status, ['cooking', 'ready', 'completed'])
                    ? $createdAt->copy()->addMinutes(rand(1, 5))
                    : null;

                $ready = in_array($status, ['ready', 'completed'])
                    ? $cookingStarted?->copy()->addMinutes(rand(3, 15))
                    : null;

                Order::withoutGlobalScopes()->create([
                    'restaurant_id'      => $restaurant->id,
                    'order_number'       => (string) rand(100, 999),
                    'status'             => $status,
                    'cooking_started_at' => $cookingStarted,
                    'ready_at'           => $ready,
                    'created_at'         => $createdAt,
                    'updated_at'         => $ready ?? $cookingStarted ?? $createdAt,
                ]);
                $created++;
            }
        }

        $this->command->info("Создано тестовых заказов: {$created}");
    }
}
