<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeOwner(string $email, string $slug): array
    {
        $user = User::create([
            'email' => $email,
            'password_hash' => Hash::make('password'),
            'role' => 'owner',
        ]);

        $restaurant = Restaurant::create([
            'name' => "Resto $slug",
            'slug' => $slug,
            'owner_id' => $user->id,
            'is_active' => true,
        ]);

        return [$user, $restaurant];
    }

    public function test_owner_cannot_see_other_tenant_orders(): void
    {
        [$ownerA, $restoA] = $this->makeOwner('a@test.by', 'resto-a');
        [$ownerB, $restoB] = $this->makeOwner('b@test.by', 'resto-b');

        Order::create(['restaurant_id' => $restoA->id, 'order_number' => 'A1', 'status' => 'pending']);
        Order::create(['restaurant_id' => $restoB->id, 'order_number' => 'B1', 'status' => 'pending']);

        $response = $this->withHeader('X-Restaurant-Id', 'resto-a')
            ->getJson('/api/kitchen/orders');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['order_number' => 'A1']);
        $response->assertJsonMissing(['order_number' => 'B1']);
    }

    public function test_guest_cannot_see_other_tenant_order_status(): void
    {
        [, $restoA] = $this->makeOwner('a@test.by', 'resto-a');
        [, $restoB] = $this->makeOwner('b@test.by', 'resto-b');

        $orderB = Order::create([
            'restaurant_id' => $restoB->id,
            'order_number' => 'B1',
            'status' => 'ready',
        ]);

        $response = $this->withHeader('X-Restaurant-Id', 'resto-a')
            ->getJson("/api/orders/status?order_id={$orderB->id}");

        $response->assertOk();
        $response->assertJson(['status' => 'not_found']);
    }
}
