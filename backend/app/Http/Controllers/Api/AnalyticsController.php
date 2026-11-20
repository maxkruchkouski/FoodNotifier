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

    public function getOrdersSeries(Request $request)
    {
        $tenant = app('tenant');
        abort_unless($tenant, 404, 'Tenant not resolved');

        $days = (int) $request->query('days', 7);
        $days = in_array($days, [7, 30], true) ? $days : 7;
        $from = \Carbon\Carbon::now()->subDays($days - 1)->startOfDay();

        $byDay = \App\Models\Order::query()
            ->where('restaurant_id', $tenant->id)
            ->where('created_at', '>=', $from)
            ->select(
                \Illuminate\Support\Facades\DB::raw("to_char(created_at, 'YYYY-MM-DD') as date"),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as orders'),
                \Illuminate\Support\Facades\DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed")
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $from->copy()->addDays($i)->format('Y-m-d');
            $series[] = [
                'date'      => $date,
                'orders'    => (int) ($byDay[$date]->orders ?? 0),
                'completed' => (int) ($byDay[$date]->completed ?? 0),
            ];
        }

        $byStatus = \App\Models\Order::query()
            ->where('restaurant_id', $tenant->id)
            ->where('created_at', '>=', $from)
            ->select('status', \Illuminate\Support\Facades\DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byHourRaw = \App\Models\Order::query()
            ->where('restaurant_id', $tenant->id)
            ->where('created_at', '>=', $from)
            ->select(
                \Illuminate\Support\Facades\DB::raw('EXTRACT(HOUR FROM created_at)::int as hour'),
                \Illuminate\Support\Facades\DB::raw('COUNT(*) as count')
            )
            ->groupBy('hour')
            ->pluck('count', 'hour')
            ->toArray();

        $byHour = array_fill(0, 24, 0);
        foreach ($byHourRaw as $hour => $count) {
            $byHour[(int) $hour] = (int) $count;
        }

        $avgCook = \App\Models\Order::query()
            ->where('restaurant_id', $tenant->id)
            ->where('created_at', '>=', $from)
            ->whereNotNull('ready_at')
            ->whereNotNull('cooking_started_at')
            ->select(\Illuminate\Support\Facades\DB::raw('ROUND(AVG(EXTRACT(EPOCH FROM (ready_at - cooking_started_at)))) as avg_sec'))
            ->value('avg_sec');

        return response()->json([
            'days'                => $days,
            'series'              => $series,
            'by_status'           => $byStatus,
            'by_hour'             => $byHour,
            'avg_cooking_seconds' => $avgCook ? (int) $avgCook : 0,
        ]);
    }

}
