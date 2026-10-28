<?php


namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    private function getRestaurantId(Request $request): string
    {
        return $request->get('restaurant_id');
    }

    public function store(Request $request)
    {
        $request->validate(['order_number' => 'required|string']);
        $orderId = DB::table('orders')->insertGetId([
            'restaurant_id' => $this->getRestaurantId($request),
            'order_number' => $request->input('order_number'),
            'status' => 'pending',
            'created_at' => Carbon::now('Europe/Minsk'),
            'updated_at' => Carbon::now('Europe/Minsk'),
        ]);
        return response()->json(['success' => true, 'order_id' => $orderId], 201);
    }

    public function checkStatus(Request $request, $orderId)
    {
        $status = DB::table('orders')->where('id', $orderId)->where('restaurant_id', $this->getRestaurantId($request))->value('status');
        return response()->json(['status' => $status ?? 'not_found']);
    }

    public function getCookingOrders(Request $request)
    {
        $orders = DB::table('orders')->select('id', 'order_number', 'status', DB::raw("to_char(created_at, 'HH24:MI') as created_at"))
            ->where('restaurant_id', $this->getRestaurantId($request))->whereIn('status', ['pending', 'cooking'])->orderBy('id', 'asc')->get();
        return response()->json($orders);
    }

    public function startCooking(Request $request, $orderId)
    {
        $updated = DB::table('orders')->where('id', $orderId)->where('restaurant_id', $this->getRestaurantId($request))
            ->update(['status' => 'cooking', 'cooking_started_at' => Carbon::now('Europe/Minsk')]);
        return response()->json(['success' => (bool)$updated]);
    }

    public function markReady(Request $request, $orderId)
    {
        $updated = DB::table('orders')->where('id', $orderId)->where('restaurant_id', $this->getRestaurantId($request))
            ->update(['status' => 'ready', 'ready_at' => Carbon::now('Europe/Minsk')]);
        return response()->json(['success' => (bool)$updated]);
    }

    public function getBoardOrders(Request $request)
    {
        $orders = DB::table('orders')->select('order_number', 'status')
            ->where('restaurant_id', $this->getRestaurantId($request))
            ->where('created_at', '>=', DB::raw("NOW() - INTERVAL '2 hours'"))
            ->whereIn('status', ['cooking', 'ready'])->orderBy('status', 'desc')->orderBy('id', 'asc')->get();
        return response()->json($orders);
    }
}
