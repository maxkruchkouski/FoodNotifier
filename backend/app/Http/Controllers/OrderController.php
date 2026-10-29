<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use Illuminate\Http\Request;
use Carbon\Carbon;

class OrderController extends Controller
{
    /**
     * Вспомогательный метод: проверка биллинга (активности ресторана)
     */
    private function isRestaurantActive(string $restaurantId): bool
    {
        $restaurant = Restaurant::query()->where('slug', $restaurantId)->first();
        return $restaurant && $restaurant->is_active;
    }

    /**
     * 1. Создание заказа гостем (action=create_order)
     */
    public function createOrder(Request $request)
    {
        $restaurantId = $request->input('restaurant_id');
        $orderNum = $request->input('order_number');

        if (empty($restaurantId)) {
            return response()->json(['error' => 'Идентификатор заведения не указан'], 400);
        }

        if (!$this->isRestaurantActive($restaurantId)) {
            return response()->json([
                'success' => false,
                'error' => 'billing_blocked',
                'message' => 'Доступ заблокирован. Оплатите тариф.'
            ], 403);
        }

        if (empty($orderNum)) {
            return response()->json(['success' => false, 'message' => 'Введите номер заказа']);
        }

        $order = Order::create([
            'restaurant_id' => $restaurantId,
            'order_number'  => $orderNum,
            'status'        => 'pending',
            'created_at'    => Carbon::now('Europe/Minsk'),
        ]);

        return response()->json([
            'success'  => true,
            'order_id' => $order->id
        ]);
    }

    /**
     * 2. Проверка статуса заказа гостем (action=check_status)
     */
    public function checkStatus(Request $request)
    {
        $restaurantId = $request->query('restaurant_id');
        $orderId = (int)$request->query('order_id');

        if (!$this->isRestaurantActive($restaurantId)) {
            return response()->json(['status' => 'billing_blocked'], 403);
        }

        $order = Order::query()->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->first();

        return response()->json([
            'status' => $order ? $order->status : 'not_found'
        ]);
    }

    /**
     * 3. Список активных заказов на кухне (action=get_cooking_orders)
     */
    public function getCookingOrders(Request $request)
    {
        $restaurantId = $request->query('restaurant_id');

        if (!$this->isRestaurantActive($restaurantId)) {
            return response()->json(['error' => 'billing_blocked'], 403);
        }

        $orders = Order::query()->whereIn('status', ['pending', 'cooking'])
            ->where('restaurant_id', $restaurantId)
            ->orderBy('id', 'asc')
            ->get(['id', 'order_number', 'status', 'created_at']);

        $formattedOrders = $orders->map(function ($order) {
            return [
                'id'           => $order->id,
                'order_number' => $order->order_number,
                'status'       => $order->status,
                'created_at'   => Carbon::parse($order->created_at)->format('H:i'),
            ];
        });

        return response()->json($formattedOrders);
    }

    /**
     * 4. Повар берет заказ в работу (action=start_cooking)
     */
    public function startCooking(Request $request)
    {
        $restaurantId = $request->input('restaurant_id');
        $orderId = (int)$request->input('order_id');

        $updated = Order::query()->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->update([
                'status'             => 'cooking',
                'cooking_started_at' => Carbon::now('Europe/Minsk'),
            ]);

        return response()->json(['success' => (bool)$updated]);
    }

    /**
     * 5. Повар переводит заказ в "Готово" (action=mark_ready)
     */
    public function markReady(Request $request)
    {
        $restaurantId = $request->input('restaurant_id');
        $orderId = (int)$request->input('order_id');

        $updated = Order::query()->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->update([
                'status'   => 'ready',
                'ready_at' => Carbon::now('Europe/Minsk'),
            ]);

        return response()->json(['success' => (bool)$updated]);
    }

    /**
     * 6. Вывод заказов на электронное табло зала (action=get_board_orders)
     */
    public function getBoardOrders(Request $request)
    {
        $restaurantId = $request->query('restaurant_id');

        if (!$this->isRestaurantActive($restaurantId)) {
            return response()->json([], 403);
        }

        $orders = Order::query()->where('restaurant_id', $restaurantId)
            ->where('created_at', '>=', Carbon::now('Europe/Minsk')->subHours(2))
            ->whereIn('status', ['cooking', 'ready'])
            ->orderBy('status', 'desc')
            ->orderBy('id', 'asc')
            ->get(['order_number', 'status']);

        return response()->json($orders);
    }
}
