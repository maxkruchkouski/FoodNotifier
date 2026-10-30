<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analytics;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Расчет счетчиков KPI заведения (Двухфакторная модель ожидания и готовки)
     */
    public function getKpiMetrics(Request $request)
    {
        $slug = $request->query('restaurant_id');
        if (empty($slug)) {
            return response()->json(['error' => 'restaurant_id не указан'], 400);
        }

        // 1. Всего визитов / сканирований QR заведения
        $totalVisits = Analytics::query()->where('restaurant_id', $slug)->count();

        // 2. Активных заказов сейчас на кухне в работе (очередь + готовка)
        $activeOrders = Order::query()->where('restaurant_id', $slug)
            ->whereIn('status', ['pending', 'cooking'])
            ->count();

        // 3. KPI 1: Среднее время ожидания повара (от чека до клика "В работу") в минутах
        $waitMinutes = Order::query()->where('restaurant_id', $slug)
            ->whereNotNull('cooking_started_at')
            ->select(DB::raw("ROUND(AVG(EXTRACT(EPOCH FROM (cooking_started_at - created_at))/60)) as avg_wait"))
            ->first()->avg_wait;

        // 4. KPI 2: Средняя чистая скорость готовки (от "В работу" до "Готов") в минутах
        $cookMinutes = Order::query()->where('restaurant_id', $slug)
            ->whereNotNull('ready_at')
            ->whereNotNull('cooking_started_at')
            ->select(DB::raw("ROUND(AVG(EXTRACT(EPOCH FROM (ready_at - cooking_started_at))/60)) as avg_cook"))
            ->first()->avg_cook;

        return response()->json([
            'total_visits' => $totalVisits,
            'active_orders' => $activeOrders,
            'avg_wait_time' => $waitMinutes !== null ? $waitMinutes . " мин" : "0 мин",
            'avg_cook_time' => $cookMinutes !== null ? $cookMinutes . " мин" : "0 мин",
        ]);
    }

    /**
     * Выгрузка исторических данных для гистограммы трафика
     */
    public function getChartData(Request $request)
    {
        $slug = $request->query('restaurant_id');
        $period = $request->query('period', 'week'); // week или month

        if (empty($slug)) {
            return response()->json([], 400);
        }

        if ($period === 'month') {
            // За последние 30 дней с форматированием DD.MM
            $chartData = Analytics::query()->where('restaurant_id', $slug)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->select(DB::raw("to_char(created_at, 'DD.MM') as label"), DB::raw("COUNT(*) as count"))
                ->groupBy('label')
                ->orderBy('label', 'asc')
                ->get();
        } else {
            // За последнюю неделю с форматированием дня недели (Dy)
            $chartData = Analytics::query()->where('restaurant_id', $slug)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->select(DB::raw("to_char(created_at, 'Dy') as label"), DB::raw("COUNT(*) as count"))
                ->groupBy('label')
                ->orderBy(DB::raw("min(created_at)"), 'asc')
                ->get();
        }

        return response()->json($chartData);
    }
}
