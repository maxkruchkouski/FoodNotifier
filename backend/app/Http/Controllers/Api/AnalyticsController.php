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
    public function getKpiMetrics(Request $request)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant not resolved');

        $totalVisits = Analytics::query()->where('restaurant_id', $tenant->id)->count();

        $activeOrders = Order::query()
            ->where('restaurant_id', $tenant->id)
            ->whereIn('status', ['pending', 'cooking'])
            ->count();

        $waitMinutes = Order::query()
            ->where('restaurant_id', $tenant->id)
            ->whereNotNull('cooking_started_at')
            ->select(DB::raw("ROUND(AVG(EXTRACT(EPOCH FROM (cooking_started_at - created_at))/60)) as avg_wait"))
            ->value('avg_wait');

        $cookMinutes = Order::query()
            ->where('restaurant_id', $tenant->id)
            ->whereNotNull('ready_at')
            ->whereNotNull('cooking_started_at')
            ->select(DB::raw("ROUND(AVG(EXTRACT(EPOCH FROM (ready_at - cooking_started_at))/60)) as avg_cook"))
            ->value('avg_cook');

        return response()->json([
            'total_visits' => $totalVisits,
            'active_orders' => $activeOrders,
            'avg_wait_time' => $waitMinutes !== null ? $waitMinutes . ' мин' : '0 мин',
            'avg_cook_time' => $cookMinutes !== null ? $cookMinutes . ' мин' : '0 мин',
        ]);
    }

    public function getChartData(Request $request)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant not resolved');

        $period = $request->query('period', 'week');

        if ($period === 'month') {
            $data = Analytics::query()
                ->where('restaurant_id', $tenant->id)
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->select(DB::raw("to_char(created_at, 'DD.MM') as label"), DB::raw('COUNT(*) as count'))
                ->groupBy('label')
                ->orderBy('label')
                ->get();
        } else {
            $data = Analytics::query()
                ->where('restaurant_id', $tenant->id)
                ->where('created_at', '>=', Carbon::now()->subDays(7))
                ->select(DB::raw("to_char(created_at, 'Dy') as label"), DB::raw('COUNT(*) as count'))
                ->groupBy('label')
                ->orderBy(DB::raw('min(created_at)'))
                ->get();
        }

        return response()->json($data);
    }
}
