<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Restaurant;
use Carbon\Carbon;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    private function tenantOrFail(): Restaurant
    {
        abort_unless(app()->bound('tenant'), 404, 'Tenant not resolved');
        return app('tenant');
    }

    public function store(Request $request)
    {
        $tenant = $this->tenantOrFail();
        $request->validate(['order_number' => 'required|string']);

        $order = Order::create([
            'restaurant_id' => $tenant->id,
            'order_number' => $request->input('order_number'),
            'status' => 'pending',
        ]);

        return response()->json(['success' => true, 'order_id' => $order->id], 201);
    }

    public function checkStatus(Request $request)
    {
        $tenant = $this->tenantOrFail();
        $orderId = (int) $request->get('order_id');

        $status = Order::query()
            ->where('id', $orderId)
            ->where('restaurant_id', $tenant->id)
            ->value('status');

        return response()->json(['status' => $status ?? 'not_found']);
    }

    public function getCookingOrders(Request $request)
    {
        $tenant = $this->tenantOrFail();

        $orders = Order::query()
            ->where('restaurant_id', $tenant->id)
            ->whereIn('status', ['pending', 'cooking'])
            ->orderBy('id')
            ->get(['id', 'order_number', 'status', 'created_at']);

        return response()->json($orders->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'created_at' => Carbon::parse($o->created_at)->format('H:i'),
        ]));
    }

    public function startCooking(Request $request)
    {
        $tenant = $this->tenantOrFail();
        $orderId = (int) ($request->input('order_id') ?: $request->input('id'));

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Не передан ID заказа'], 422);
        }

        $updated = Order::query()
            ->where('id', $orderId)
            ->where('restaurant_id', $tenant->id)
            ->update([
                'status' => 'cooking',
                'cooking_started_at' => Carbon::now('Europe/Minsk'),
            ]);

        return response()->json(['success' => (bool) $updated]);
    }

    public function markReady(Request $request)
    {
        $tenant = $this->tenantOrFail();
        $orderId = (int) ($request->input('order_id') ?: $request->input('id'));

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Не передан ID заказа'], 422);
        }

        $updated = Order::query()
            ->where('id', $orderId)
            ->where('restaurant_id', $tenant->id)
            ->update([
                'status' => 'ready',
                'ready_at' => Carbon::now('Europe/Minsk'),
            ]);

        return response()->json(['success' => (bool) $updated]);
    }

    public function getBoardOrders(Request $request)
    {
        $tenant = $this->tenantOrFail();

        Order::query()
            ->where('restaurant_id', $tenant->id)
            ->where('status', 'ready')
            ->where('ready_at', '<', Carbon::now('Europe/Minsk')->subMinutes(10))
            ->update(['status' => 'completed']);

        $orders = Order::query()
            ->where('restaurant_id', $tenant->id)
            ->whereIn('status', ['pending', 'cooking', 'ready'])
            ->orderBy('id')
            ->get(['id', 'order_number', 'status', 'created_at']);

        return response()->json($orders->map(fn ($o) => [
            'id' => $o->id,
            'order_number' => $o->order_number,
            'status' => $o->status,
            'created_at' => Carbon::parse($o->created_at)->format('H:i'),
        ]));
    }

    public function getActiveRestaurants()
    {
        $restaurants = Restaurant::withoutGlobalScope('owner')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        return response()->json(['success' => true, 'data' => $restaurants]);
    }
}
