<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Restaurant;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $restaurants = Restaurant::orderBy('id', 'desc')->get();
        $selectedSlug = $request->query('stats', $restaurants->first()?->slug);
        $period = $request->query('period', 'week');

        $metrics = [
            'count_total_visits' => 0, 'count_active_orders' => 0,
            'avg_wait_time' => '0 мин', 'avg_cook_time' => '0 мин', 'chart_data' => []
        ];

        if ($selectedSlug) {
            $metrics['count_total_visits'] = DB::table('analytics')->where('restaurant_id', $selectedSlug)->count();

            $metrics['count_active_orders'] = DB::table('orders')
                ->where('restaurant_id', $selectedSlug)
                ->whereIn('status', ['pending', 'cooking'])->count();

            $waitMinutes = DB::table('orders')->where('restaurant_id', $selectedSlug)->whereNotNull('cooking_started_at')
                ->selectRaw('ROUND(AVG(EXTRACT(EPOCH FROM (cooking_started_at - created_at))/60)) as avg_wait')->value('avg_wait');
            if ($waitMinutes !== null) $metrics['avg_wait_time'] = $waitMinutes . " мин";

            $cookMinutes = DB::table('orders')->where('restaurant_id', $selectedSlug)->whereNotNull('ready_at')->whereNotNull('cooking_started_at')
                ->selectRaw('ROUND(AVG(EXTRACT(EPOCH FROM (ready_at - cooking_started_at))/60)) as avg_cook')->value('avg_cook');
            if ($cookMinutes !== null) $metrics['avg_cook_time'] = $cookMinutes . " мин";

            $interval = $period === 'month' ? '30 days' : '7 days';
            $format = $period === 'month' ? 'DD.MM' : 'Dy';

            $metrics['chart_data'] = DB::table('analytics')
                ->where('restaurant_id', $selectedSlug)
                ->where('created_at', '>=', DB::raw("CURRENT_TIMESTAMP - INTERVAL '$interval'"))
                ->selectRaw("to_char(created_at, '$format') as label, COUNT(*) as count")
                ->groupBy('label')->orderBy('label', 'asc')->get();
        }

        return response()->json(['restaurants' => $restaurants, 'metrics' => $metrics]);
    }

    public function storeRestaurant(Request $request)
    {
        $validated = $request->validate(['slug' => 'required|string|unique:restaurants,slug', 'name' => 'required|string']);
        $restaurant = Restaurant::create(['slug' => $validated['slug'], 'name' => $validated['name'], 'owner_id' => null, 'is_active' => true]);
        return response()->json(['status' => 'success', 'data' => $restaurant], 201);
    }

    public function toggleActive($id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->is_active = !$restaurant->is_active;
        $restaurant->save();
        return response()->json(['status' => 'success', 'is_active' => $restaurant->is_active]);
    }

    public function destroyRestaurant($id)
    {
        Restaurant::findOrFail($id)->delete();
        return response()->json(['status' => 'success']);
    }

    public function track(Request $request)
    {
        DB::table('analytics')->insert([
            'restaurant_id' => $request->input('restaurant_id', 'default_cafe'),
            'created_at' => Carbon::now('Europe/Minsk')
        ]);
        return response()->json(['success' => true]);
    }
}
