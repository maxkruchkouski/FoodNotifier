<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Restaurant;

class OrderController extends Controller
{
    /**
     * Вспомогательный метод для получения ID ресторана по его slug из запроса
     */
    private function getRestaurantId(Request $request): ?int
    {
        $slugOrId = $request->get('restaurant_id') ?: $request->get('restaurant');

        if (is_numeric($slugOrId)) {
            return (int)$slugOrId;
        }

        if ($slugOrId) {
            return DB::table('restaurants')->where('slug', $slugOrId)->value('id');
        }

        return null;
    }

    /**
     * Создание заказа (POST /api/orders)
     */
    public function store(Request $request)
    {
        $request->validate(['order_number' => 'required|string']);

        $restaurantId = $this->getRestaurantId($request);
        if (!$restaurantId) {
            return response()->json(['success' => false, 'message' => 'Ресторан не найден'], 404);
        }

        $orderId = DB::table('orders')->insertGetId([
            'restaurant_id' => $restaurantId,
            'order_number' => $request->input('order_number'),
            'status' => 'pending',
            'created_at' => Carbon::now('Europe/Minsk'),
            'updated_at' => Carbon::now('Europe/Minsk'),
        ]);

        return response()->json(['success' => true, 'order_id' => $orderId], 201);
    }

    /**
     * Проверка статуса заказа для гостя
     */
    public function checkStatus(Request $request)
    {
        $orderId = $request->get('order_id');
        $restaurantId = $this->getRestaurantId($request);

        $status = DB::table('orders')
            ->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->value('status');

        return response()->json(['status' => $status ?? 'not_found']);
    }

    /**
     * Получение заказов СТРОГО для экрана кухни (только pending и cooking)
     * GET /api/kitchen/orders
     */
    public function getCookingOrders(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);

        $orders = DB::table('orders')
            ->select('id', 'order_number', 'status', DB::raw("to_char(created_at, 'HH24:MI') as created_at"))
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['pending', 'cooking'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Перевод заказа в статус "Готовится" (POST /api/kitchen/orders/start)
     */
    public function startCooking(Request $request)
    {
        $orderId = $request->input('order_id') ?: $request->input('id');
        $restaurantId = $this->getRestaurantId($request);

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Не передан ID заказа'], 422);
        }

        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->update([
                'status' => 'cooking',
                'cooking_started_at' => Carbon::now('Europe/Minsk'),
                'updated_at' => Carbon::now('Europe/Minsk')
            ]);

        return response()->json(['success' => (bool)$updated]);
    }

    /**
     * Перевод заказа в статус "Готово" (POST /api/kitchen/orders/ready)
     */
    public function markReady(Request $request)
    {
        $orderId = $request->input('order_id') ?: $request->input('id');
        $restaurantId = $this->getRestaurantId($request);

        if (!$orderId) {
            return response()->json(['success' => false, 'message' => 'Не передан ID заказа'], 422);
        }

        $updated = DB::table('orders')
            ->where('id', $orderId)
            ->where('restaurant_id', $restaurantId)
            ->update([
                'status' => 'ready',
                'ready_at' => Carbon::now('Europe/Minsk'),
                'updated_at' => Carbon::now('Europe/Minsk')
            ]);

        return response()->json(['success' => (bool)$updated]);
    }

    /**
     * Получение заказов СТРОГО для электронного табло (pending + cooking + ready)
     * Включает автоматическое завершение заказов через 10 минут
     * GET /api/board/orders
     */
    public function getBoardOrders(Request $request)
    {
        $restaurantId = $this->getRestaurantId($request);
        if (!$restaurantId) {
            return response()->json([]);
        }

        // АВТО-АРХИВАЦИЯ: Если статус ready и прошло больше 10 минут от ready_at — переводим в completed
        DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', 'ready')
            ->where('ready_at', '<', Carbon::now('Europe/Minsk')->subMinutes(10))
            ->update([
                'status' => 'completed',
                'updated_at' => Carbon::now('Europe/Minsk')
            ]);

        // Выбираем только те, что должны остаться на экране
        $orders = DB::table('orders')
            ->select('id', 'order_number', 'status', DB::raw("to_char(created_at, 'HH24:MI') as created_at"))
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['pending', 'cooking', 'ready'])
            ->orderBy('id', 'asc')
            ->get();

        return response()->json($orders);
    }

    /**
     * Получить список активных ресторанов
     */
    public function getActiveRestaurants()
    {
        try {
            $restaurants = Restaurant::query()
                ->where('is_active', true)
                ->orderBy('name', 'asc')
                ->get(['slug', 'name']);

            return response()->json([
                'success' => true,
                'data' => $restaurants ?? []
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => true,
                'data' => [],
                'debug_message' => 'База данных пуста или не инициализирована'
            ]);
        }
    }
}
