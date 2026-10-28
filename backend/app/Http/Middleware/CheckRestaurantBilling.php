<?php


namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class CheckRestaurantBilling
{
    public function handle(Request $request, Closure $next): Response
    {
        $restaurantId = $request->header('X-Restaurant-Id')
            ?? $request->input('restaurant_id')
            ?? $request->route('restaurant_id');

        if (empty($restaurantId)) {
            return response()->json(['error' => 'Идентификатор заведения (restaurant_id) не указан'], 400);
        }

        $isActive = DB::table('restaurants')->where('slug', $restaurantId)->value('is_active');

        if ($isActive === null || (bool)$isActive === false) {
            return response()->json([
                'success' => false,
                'error' => 'billing_blocked',
                'message' => 'Доступ заблокирован. Оплатите тариф.'
            ], 403);
        }

        $request->attributes->set('restaurant_id', $restaurantId);
        return $next($request);
    }
}
